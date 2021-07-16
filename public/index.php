<?php

define("ROOT_DIR", __DIR__ . "/../");
define("LANG_DIR", ROOT_DIR . "lang/");
define("LOG_DIR", ROOT_DIR . "log/");
define("LOG_FILE", LOG_DIR . "app.log");
define("SRC_DIR", ROOT_DIR . "src/");
define("VENDOR_DIR", ROOT_DIR . "vendor/");

require VENDOR_DIR . "autoload.php";

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

define("ENV", require ROOT_DIR . ".env.php");
define("LANG", require LANG_DIR . (ENV["LANG"] ?? "en") . ".php");

// Helpers

function sanitizeUrl(string $uri): string {
    if (false !== $pos = strpos($uri, "?"))
        $uri = substr($uri, 0, $pos);
    
    return rawurldecode($uri);
}

function getParsedBody(): void {
    $body = file_get_contents("php://input");

    if ($body === false)
        throw new Exception(ENV["ERROR_CATCHING_BODY"]);

    return json_decode($body, true) ?? [];
}

function jsonResponse(Array $response = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    header("Content-Type: application/json");

    echo json_encode($response);
}

function successResponse(Array $data = []): void {
    $response = [ "message" => "Success." ];

    if ($data != [])
        $response["data"] = $data;

    jsonResponse($response);
}

function errorResponse(String $message): void {
    $message = $message ?? LANG["REQUEST_ERROR"];
    jsonResponse([ "message" => $message ], 400);
}

// Initializing

$transporter = new Swift_SmtpTransport(ENV["MAIL_SERVER"], ENV["MAIL_PORT"], ENV["MAIL_SECURITY"]);
$transporter->setUsername(ENV["MAIL_USER"]);
$transporter->setPassword(ENV["MAIL_PASSWORD"]);

try {
    if (!is_dir(LOG_DIR) && !mkdir(LOG_DIR))
        throw new Exception(ENV["ERROR_NO_LOGDIR"]));
}
catch (Throwable $th) {
    errorResponse($th->getMessage());
    exit;
}

$logger = new Monolog\Logger(ENV["APP_NAME"]);
$logger->pushHandler(new Monolog\Handler\StreamHandler(LOG_FILE, Monolog\Logger::DEBUG));
$mailer = new Swift_Mailer($transporter);

if (ENV["NOTIFY_CRITICAL_LOG"]) {
    $notifyCriticalLog = (new Swift_Message(ENV["APP_NAME"] . ": A CRITICAL log was added"));
    $notifyCriticalLog->setFrom([ENV["MAIL_USER"] => ENV["APP_NAME"]]);
    $notifyCriticalLog->setTo(is_array(ENV["MAIL_ALERT"]) ? ENV["MAIL_ALERT"] : [ENV["MAIL_ALERT"]]);

    $logger->pushHandler(new Monolog\Handler\SwiftMailerHandler($mailer, $notifyCriticalLog, Monolog\Logger::CRITICAL, false));
}

// Actions

function newContact($params) {
    global $mailer, $logger;

    $json = getParsedBody();

    if (!$json["origin"] || $json["origin"] == ""
        || !$json["name"] || $json["name"] == ""
        || !$json["comments"] || $json["comments"] == "")
            throw new Exception(ENV["ERROR_MISSING_REQUIRED_FIELDS"]);

    $id = uniqid();
    $logger->info("New contact #" . $id, $json);

    $message = (new Swift_Message(LANG["MAIL_TITLE"] . " #" . $id . " - " . ($json["origin"] ?? LANG["MAIL_NOT_INFORMED"])))
        ->setFrom([ENV["MAIL_USER"] => ENV["APP_NAME"]])
        ->setTo(ENV["MAIL_ALERT"])
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
        successResponse();
    }
    catch (Throwable $th) {
        throw new Exception(ENV["ERROR_SENDING_MAIL"]);
    }
}

// Routes

$logger->info("Start app.");

$httpMethod = $_SERVER["REQUEST_METHOD"];
$uri = sanitizeUrl($_SERVER["REQUEST_URI"]);

try {
    $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
        $r->addGroup(ENV["BASE_ROUTE"], function(FastRoute\RouteCollector $r) {
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
            $logger->errorResponse("Redirected to default route.");
            header("Location: " . ENV["APP_URL_REDIRECT"]);
    }
}
catch (Throwable $th) {
    $logger->error("Error: " . $th->getMessage());
    errorResponse($th->getMessage());
}
