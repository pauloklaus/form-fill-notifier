<?php

define("ROOT_DIR", __DIR__ . "/../");
define("LANG_DIR", ROOT_DIR . "lang/");
define("LOG_DIR", ROOT_DIR . "log/");
define("LOG_FILE", LOG_DIR . "app.log");
define("SRC_DIR", ROOT_DIR . "src/");
define("VENDOR_DIR", ROOT_DIR . "vendor/");

require VENDOR_DIR . "autoload.php";

define("ERROR_CATCHING_BODY", 1492);
define("ERROR_DECODING_JSON", 1625);
define("ERROR_NO_LOGDIR", 6512);
define("ERROR_MISSING_REQUIRED_FIELDS", 9121);
define("ERROR_SENDING_MAIL", 2183);

// Http Headers

header_remove("X-Powered-By");

if (isset($_SERVER["HTTP_ORIGIN"]))
    header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Content-Type: text/plain");
    exit;
}

// Config

$_ENV = require ROOT_DIR . ".env.php";
DEFINE("LANG", require LANG_DIR . ($_ENV["LANG"] ?? "en") . ".php");

// Helpers

function failMessage(int $code = 0) {
    return "#" . $code . ": " . LANG["FAIL_MESSAGE"];
}

function getParsedBody() {
    global $logger;

    $body = file_get_contents("php://input");

    if ($body === false) {
        $logger->critical("Error catching requested body.");
        throw new Exception(failMessage(ERROR_CATCHING_BODY), 1);
    }

    try {
        return json_decode($body, true) ?? [];
    }
    catch (Throwable $th)  {
        $logger->critical("Error decoding json body: ", $th->getMessage());
        throw new Exception(failMessage(ERROR_DECODING_JSON), 1);
    }
}

function jsonResponse(Array $response = [], int $statusCode = 200) {
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode($response);
}

function success(Array $data = []) {
    $response = [ "message" => "Success." ];

    if ($data != [])
        $response["data"] = $data;

    jsonResponse($response);
}

function error(String $message) {
    $message = $message ?? LANG["REQUEST_ERROR"];
    jsonResponse([ "message" => $message ], 400);
}

// Initializing

$transporter = new Swift_SmtpTransport($_ENV["MAIL_SERVER"], $_ENV["MAIL_PORT"], $_ENV["MAIL_SECURITY"]);
$transporter->setUsername($_ENV["MAIL_USER"]);
$transporter->setPassword($_ENV["MAIL_PASSWORD"]);

try {
    if (!is_dir(LOG_DIR) && !mkdir(LOG_DIR))
        throw new Exception(failMessage(ERROR_NO_LOGDIR));
}
catch (Throwable $th) {
    error($th->getMessage());
    exit;
}

$logger = new Monolog\Logger($_ENV["APP_NAME"]);
$logger->pushHandler(new Monolog\Handler\StreamHandler(LOG_FILE, Monolog\Logger::DEBUG));
$mailer = new Swift_Mailer($transporter);

if ($_ENV["NOTIFY_CRITICAL_LOG"]) {

    $notifyCriticalLog = (new Swift_Message($_ENV["APP_NAME"] . ": A CRITICAL log was added"));
    $notifyCriticalLog->setFrom([$_ENV["MAIL_USER"] => $_ENV["APP_NAME"]]);
    $notifyCriticalLog->setTo(is_array($_ENV["MAIL_ALERT"]) ? $_ENV["MAIL_ALERT"] : [$_ENV["MAIL_ALERT"]]);

    $logger->pushHandler(new Monolog\Handler\SwiftMailerHandler($mailer, $notifyCriticalLog, Monolog\Logger::CRITICAL, false));
}

// Actions

function newContact($params) {
    global $mailer, $logger;

    $json = getParsedBody();

    if (!$json["origin"] || $json["origin"] == ""
        || !$json["name"] || $json["name"] == ""
        || !$json["comments"] || $json["comments"] == "") {
            $logger->critical("Missing required fields.", $json);
            error(failMessage(ERROR_MISSING_REQUIRED_FIELDS));
            return;
        }

    $id = uniqid();
    $logger->info("New contact #" . $id, $json);

    $message = (new Swift_Message(LANG["MAIL_TITLE"] . " #" . $id . " - " . ($json["origin"] ?? LANG["MAIL_NOT_INFORMED"])))
        ->setFrom([$_ENV["MAIL_USER"] => $_ENV["APP_NAME"]])
        ->setTo($_ENV["MAIL_ALERT"])
        ->setReplyTo($json["email"] ?? "")
        ->setBody(LANG["MAIL_TITLE"] . PHP_EOL . PHP_EOL .
            "Id: #" . $id . PHP_EOL .
            LANG["MAIL_SOURCE"] . ": " . ($json["origin"] ?? LANG["MAIL_NOT_INFORMED"]) . PHP_EOL .
            LANG["MAIL_NAME"] . ": " . ($json["name"] ?? LANG["MAIL_NOT_INFORMED"]) . PHP_EOL .
            LANG["MAIL_EMAIL"] . ": " . ($json["email"] ?? LANG["MAIL_NOT_INFORMED"]) . PHP_EOL .
            LANG["MAIL_PHONE"] . ": " . ($json["phone"] ?? LANG["MAIL_NOT_INFORMED"]) . PHP_EOL .
            LANG["MAIL_COMMENT"] . ": " . ($json["comments"] ?? LANG["MAIL_NOT_INFORMED"])
        );
    try {
        $mailer->send($message);
        success();
    }
    catch (Throwable $th) {
        $logger->error("Error sending new contact mail notification: " . $th->getMessage());
        error(failMessage(ERROR_SENDING_MAIL));
    }
}

// Routes

$logger->info("Start app.");

$httpMethod = $_SERVER["REQUEST_METHOD"];
$uri = $_SERVER["REQUEST_URI"];

if (false !== $pos = strpos($uri, "?"))
    $uri = substr($uri, 0, $pos);

$uri = rawurldecode($uri);

try {
    $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
        $r->addGroup($_ENV["BASE_ROUTE"], function(FastRoute\RouteCollector $r) {
	        $r->addRoute("OPTIONS", "contact", "success");
    	    $r->addRoute("POST", "contact", "newContact");
		});
    });

    $logger->info("Get route method. " . $httpMethod . " " . $uri);
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    $logger->info("Run route method.", $routeInfo);
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];
            $handler($var);
            break;
        case FastRoute\Dispatcher::NOT_FOUND:
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        default:
            // $allowedMethods = $routeInfo[1];
            $logger->error("Run default route.");
            header("Location: " . $_ENV["APP_URL_REDIRECT"]);
            break;
    }
}
catch (Throwable $th) {
    error($th->getMessage());
}
