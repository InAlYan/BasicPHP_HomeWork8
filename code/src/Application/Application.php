<?php

namespace Geekbrains\Application1\Application;

use Exception;
use Geekbrains\Application1\Domain\Controllers\AbstractController;
use Geekbrains\Application1\Domain\Models\User;
use Geekbrains\Application1\Infrastructure\Config;
use Geekbrains\Application1\Infrastructure\Storage;
use Geekbrains\Application1\Application\Auth;
use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class Application {

    private const APP_NAMESPACE = 'Geekbrains\Application1\Domain\Controllers\\';

    private string $controllerName;
    private string $methodName;

    public static Config $config;

    public static Storage $storage;

    public static Auth $auth;

    public static Logger $logger;

    public function __construct(){
        Application::$config = new Config();
        Application::$storage = new Storage();
        Application::$auth = new Auth();

        Application::$logger = new Logger('application_logger');
        Application::$logger->pushHandler(new StreamHandler(
            $_SERVER['DOCUMENT_ROOT'].'/log/'.Application::$config->get()['log']['LOGS_FILE'] . "-" . date("Y-m-d") . ".log",
            Level::Debug));

        Application::$logger->pushHandler(new FirePHPHandler());
    }

    public function run() : string {

        session_start();

        // Восстанавливаем сессию, если есть cookie user-token и нет сессии
        $this::$auth->restoreSession();

        $routeArray = explode('/', $_SERVER['REQUEST_URI']);

        if(isset($routeArray[1]) && $routeArray[1] != '') {
            $controllerName = $routeArray[1];
        }
        else{
            $controllerName = "page";
        }

        $this->controllerName = Application::APP_NAMESPACE . ucfirst($controllerName) . "Controller";

        if(class_exists($this->controllerName)){

            if(isset($routeArray[2]) && $routeArray[2] != '') {
                $methodName = $routeArray[2];
            }
            else {
                $methodName = "index";
            }

            $this->methodName = "action" . ucfirst($methodName);

            if(method_exists($this->controllerName, $this->methodName)){
                $controllerInstance = new $this->controllerName();
                if ($controllerInstance instanceof AbstractController) {
                    if ($this->checkAccessToMethod($controllerInstance, $this->methodName)) {
                        return call_user_func_array(
                            [$controllerInstance, $this->methodName],
                            []
                        );
                    } else {
                        Application::logErrorToFile("Попытка доступа без прав к методу ".$this->methodName.
                                                               " контроллера ".$controllerName.
                                                               Application::commonTailOfLogMessage());

                        return "Нет доступа к методу";
                    }
                } else {
                    return call_user_func_array(
                        [$controllerInstance, $this->methodName],
                        []
                    );
                }
            }
            else {
                Application::logErrorToFile("Попытка доступа к несуществующему методу ".$this->methodName.
                                                       " контроллера ".$controllerName.
                                                       Application::commonTailOfLogMessage());

                throw new Exception("Метод " .  $this->methodName . " не существует");
            }
        }
        else{
            Application::logErrorToFile("Попытка доступа к несуществующему контроллеру ".$controllerName.
                                                   Application::commonTailOfLogMessage());

            throw new Exception("Класс $this->controllerName не существует");
        }
    }

    private function checkAccessToMethod(AbstractController $controllerInstance, string $methodName) : bool {

        // Так как в момент аутентификции роли пользователя еще не известны, то всегда разрешаю эти методы
        if ($methodName === 'actionAuth' ||
            $methodName === 'actionLogin') return true;

        $userRoles = $controllerInstance->getUserRoles();

        $rules = $controllerInstance->getActionsPermissions($methodName);

        $isAllowed = false;

        if (!empty($rules))
            foreach ($rules as $rolePermission) {
                if (in_array($rolePermission, $userRoles)) {
                    $isAllowed = true;
                    break;
                }
            }

        return $isAllowed;
    }

    public static function commonTailOfLogMessage() : string {
        return " пользователем ".
            ($_SESSION['user_name'] ?? 'имя неопределено') . " " .
            ($_SESSION['user_lastname'] ?? 'фамилия неопределена') . " " .
            ($_SESSION['id_user'] ?? 'id неопределен') . " | Попытка вызова адреса ".$_SERVER['REQUEST_URI'];
    }

    public static function logErrorToFile($logMessage): void {
        Application::$logger->error($logMessage);
    }

}