<?php
require 'vendor/autoload.php';

use React\Http\HttpServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use React\MySQL\Factory;
use React\Promise\Promise;

$loop = Loop::get();
$factory = new Factory($loop);
$connection = $factory->createLazyConnection('root:@127.0.0.1:3306/reactphp_db');

// Crear el servidor HTTP
$server = new HttpServer(function (ServerRequestInterface $request) use ($connection) {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    try {
        // Manejo de las rutas
        switch ($path) {
            case '/':
                return serveStaticFile(__DIR__ . '/public/index.html', 'text/html');
            case '/contact':
                return serveStaticFile(__DIR__ . '/public/contact.html', 'text/html');
            case '/style.css':
                return serveStaticFile(__DIR__ . '/public/style.css', 'text/css');
            case '/img/mision.jpg':  // Servir imagen de logo
                return serveStaticFile(__DIR__ . '/public/img/mision.jpg', 'image/jpg');
            case '/img/empresa1.jpg': // Servir imagen de empresa
                return serveStaticFile(__DIR__ . '/public/img/empresa1.jpg', 'image/jpg');
            case '/img/Banner1.jpg': // Servir imagen de empresa
                return serveStaticFile(__DIR__ . '/public/img/Banner1.jpg', 'image/jpg');
            case '/img/Banner2.jpg': // Servir imagen de empresa
                return serveStaticFile(__DIR__ . '/public/img/Banner2.jpg', 'image/jpg');
            case '/img/Banner3.jpg': // Servir imagen de empresa
                return serveStaticFile(__DIR__ . '/public/img/Banner3.jpg', 'image/jpg');
            case '/data':
                return fetchData($connection);
            case '/data-file':
                return serveStaticFile(__DIR__ . '/public/data.txt', 'text/plain');
            case '/create':
                if ($method === 'POST') return createProduct($request, $connection);
                break;
            case '/delete':
                if ($method === 'POST') return deleteProduct($request, $connection);
                break;
            case '/update':
                if ($method === 'POST') return updateProduct($request, $connection);
                break;
            case '/contact-form':
                if ($method === 'POST') return ContactForm($request, $connection);
                break;
                
            default:
                return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], 'Pagina no encontrada- ERROR 404');
        }
    } catch (Exception $e) {
        return new React\Http\Message\Response(500, ['Content-Type' => 'text/plain'], 'Error general 500: ' . $e->getMessage());
    }
});

// Función para servir archivos estáticos
function serveStaticFile($path, $contentType) {
    if (!file_exists($path)) {
        return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], 'Archivo no encontrado');
    }

    $contents = file_get_contents($path);
    return new React\Http\Message\Response(200, ['Content-Type' => $contentType], $contents);
}

// Obtener datos de la base de datos (productos)
function fetchData($connection) {
    return $connection->query('SELECT * FROM productos')->then(
        function (React\MySQL\QueryResult $result) {
            return new React\Http\Message\Response(200, ['Content-Type' => 'application/json'], json_encode($result->resultRows));
        },
        function (Exception $error) {
            return new React\Http\Message\Response(500, ['Content-Type' => 'text/plain'], 'Error en la base de datos: ' . $error->getMessage());
        }
    );
}

// Crear un producto en la base de datos
function createProduct($request, $connection) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (empty($data['nombre']) || empty($data['precio']) || !is_numeric($data['precio'])) {
        return new React\Http\Message\Response(400, ['Content-Type' => 'text/plain'], 'Datos inválidos');
    }
    return $connection->query('INSERT INTO productos (nombre, precio) VALUES (?, ?)', [$data['nombre'], $data['precio']])
        ->then(fn() => new React\Http\Message\Response(201, ['Content-Type' => 'text/plain'], 'Producto creado'));
}

// Eliminar un producto de la base de datos
function deleteProduct($request, $connection) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (empty($data['id']) || !is_numeric($data['id'])) {
        return new React\Http\Message\Response(400, ['Content-Type' => 'text/plain'], 'ID inválido');
    }
    return $connection->query('DELETE FROM productos WHERE id = ?', [$data['id']])
        ->then(fn() => new React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], 'Producto eliminado'));
}

// Actualizar un producto en la base de datos
function updateProduct($request, $connection) {
    $data = json_decode($request->getBody()->getContents(), true);
    if (empty($data['id']) || empty($data['nombre']) || empty($data['precio'])) {
        return new React\Http\Message\Response(400, ['Content-Type' => 'text/plain'], 'Datos inválidos');
    }
    return $connection->query('UPDATE productos SET nombre = ?, precio = ? WHERE id = ?', [$data['nombre'], $data['precio'], $data['id']])
        ->then(fn() => new React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], 'Producto actualizado'));
}
// Función para manejar el formulario de contacto y agregar los datos a la base de datos
function ContactForm($request, $connection) {
    $data = json_decode($request->getBody()->getContents(), true);

    // Verificamos que todos los campos estén presentes
    if (empty($data['nombre']) || empty($data['email']) || empty($data['mensaje'])) {
        return new React\Http\Message\Response(400, ['Content-Type' => 'text/plain'], 'Todos los campos son obligatorios');
    }

    // Intentamos insertar los datos en la base de datos
    return $connection->query('INSERT INTO mensajes_contacto (nombre, email, mensaje) VALUES (?, ?, ?)', [
        $data['nombre'],
        $data['email'],
        $data['mensaje']
    ])
    ->then(function () {
        // Si la inserción es exitosa, devolvemos un mensaje de éxito
        return new React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], 'Mensaje recibido. Nos pondremos en contacto contigo pronto.');
    })
    ->otherwise(function (Exception $e) {
        // Capturamos cualquier error y lo mostramos
        $errorMessage = $e->getMessage();
        error_log('Error al guardar el mensaje: ' . $errorMessage); // Escribe el error en el log
        return new React\Http\Message\Response(500, ['Content-Type' => 'text/plain'], 'Error al guardar el mensaje en la base de datos: ' . $errorMessage);
    });
}



// Inicializa el servidor en el puerto 8080
$socket = new SocketServer('0.0.0.0:8080');
$server->listen($socket);

echo "Servidor escuchando en http://localhost:8080\n";
$loop->run();
