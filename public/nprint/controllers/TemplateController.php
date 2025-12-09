<?php
namespace App\Controllers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\TemplateModel;
use Exception;

class TemplateController {
    private $model;

    public function __construct() {
     
    }


    public function getAll(Request $request, Response $response) {
        try {
            $templates = (new TemplateModel())->getTemplates();
            if (!is_array($templates)) {
                throw new Exception('No se pudo obtener la lista de templates');
            }
            // Decodificar los campos JSON
            foreach ($templates as &$template) {
                if (isset($template['template_json'])) {
                    $template['template_json'] = json_decode($template['template_json'], true);
                }
                if (isset($template['example_json'])) {
                    $template['example_json'] = json_decode($template['example_json'], true);
                }
            }
            $result = [
                'success' => 1,
                'message' => 'Templates obtenidos correctamente',
                'data' => $templates
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $result = [
                'success' => 0,
                'message' => $e->getMessage(),
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function getById(Request $request, Response $response, $args) {
        try {
            $template = (new TemplateModel())->getTemplateById($args['id']);
            if (!$template) {
                throw new Exception('Template no encontrado');
            }
            // Decodificar los campos JSON
            if (isset($template['template_json'])) {
                $template['template_json'] = json_decode($template['template_json'], true);
            }
            if (isset($template['example_json'])) {
                $template['example_json'] = json_decode($template['example_json'], true);
            }
            $result = [
                'success' => 1,
                'message' => 'Template encontrado',
                'data' => $template
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $result = [
                'success' => 0,
                'message' => $e->getMessage(),
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function create(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';
            $caracteres = $data['caracteres'] ?? 48;
            $paperWidthPx = $data['paperWidthPx'] ?? ($caracteres * 8); // Valor por defecto si no se proporciona
            $templateJson = $data['template'] ?? '';
            $exampleJson = $data['json'] ?? '';
            if (!$name || !$templateJson || !$exampleJson) {
                throw new Exception('Faltan campos requeridos');
            }

            $templateJson = $data['template'] ?? '';
            if (is_array($templateJson)) {
                $templateJson = json_encode($templateJson, JSON_UNESCAPED_UNICODE);
            }

            $id = (new TemplateModel())->insertTemplate($name, $caracteres, $paperWidthPx ,$templateJson, $exampleJson);
            $result = [
                'success' => 1,
                'message' => 'Template creado correctamente',
                'data' => ['id' => $id]
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $result = [
                'success' => 0,
                'message' => $e->getMessage(),
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function update(Request $request, Response $response, $args) {
        try {
            // Obtener ID de los argumentos
            $id = $args['id'] ?? null;

            // IMPORTANTE: Leer el cuerpo RAW solo UNA vez y guardarlo
            $rawBody = (string) $request->getBody();
            
            // Resetear el stream para que getParsedBody() pueda leerlo
            $request->getBody()->rewind();
            
            // Obtener datos parseados
            $data = $request->getParsedBody();
            
            // Si getParsedBody() no funciona, usar el raw body guardado
            if (empty($data) && !empty($rawBody)) {
                $data = json_decode($rawBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('JSON inválido: ' . json_last_error_msg());
                }
            }

            // Log para debug (usando variables guardadas)
            error_log("Update Template - ID: " . $id);
            error_log("Update Template - Raw Body Length: " . strlen($rawBody));
            error_log("Update Template - Raw Body Content: " . $rawBody);
            error_log("Update Template - Parsed Data: " . json_encode($data));
            error_log("Update Template - Content-Type: " . $request->getHeaderLine('Content-Type'));

            // Validar que se recibió el ID
            if (!$id) {
                throw new Exception('ID de template requerido');
            }

            // Validar que se recibieron datos
            if (empty($data) || !is_array($data)) {
                throw new Exception('No se recibieron datos para actualizar. Raw body length: ' . strlen($rawBody));
            }

            // Extraer campos
            $name = $data['name'] ?? '';
            $caracteres = $data['caracteres'] ?? 48;
            $paperWidthPx = $data['paperWidthPx'] ?? ($caracteres * 8); // Valor por defecto si no se proporciona
            $templateJson = $data['template'] ?? '';
            $exampleJson = $data['json'] ?? '';

            // Validar campos requeridos
            if (empty($name) || empty($templateJson) || empty($exampleJson)) {
                throw new Exception('Faltan campos requeridos: name, template, json. Recibidos: ' . implode(', ', array_keys($data)));
            }

            // Convertir arrays a JSON si es necesario
            if (is_array($templateJson)) {
                $templateJson = json_encode($templateJson, JSON_UNESCAPED_UNICODE);
            }

            if (is_array($exampleJson)) {
                $exampleJson = json_encode($exampleJson, JSON_UNESCAPED_UNICODE);
            }

            // Actualizar en base de datos
            $success = (new TemplateModel())->updateTemplate($id, $name, $caracteres, $paperWidthPx,$templateJson, $exampleJson);
            if (!$success) {
                throw new Exception('Template no encontrado o no se pudo actualizar');
            }

            // Respuesta exitosa
            $result = [
                'success' => 1,
                'message' => 'Template actualizado correctamente',
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            // Log del error para debug
            error_log("Update Template Error: " . $e->getMessage());
            
            // Obtener información adicional para debug
            $allHeaders = [];
            foreach ($request->getHeaders() as $name => $values) {
                $allHeaders[$name] = implode(', ', $values);
            }
            
            $result = [
                'success' => 0,
                'message' => $e->getMessage(),
                'debug' => [
                    'id' => $args['id'] ?? 'missing',
                    'method' => $request->getMethod(),
                    'content_type' => $request->getHeaderLine('Content-Type'),
                    'raw_body_length' => isset($rawBody) ? strlen($rawBody) : 0,
                    'raw_body_preview' => isset($rawBody) ? substr($rawBody, 0, 200) : 'not_available',
                    'parsed_body' => $request->getParsedBody(),
                    'all_headers' => $allHeaders,
                    'server_vars' => [
                        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
                        'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown'
                    ]
                ]
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function delete(Request $request, Response $response, $args) {
        try {
            $id = $args['id'];
            $success = (new TemplateModel())->deleteTemplate($id);
            if (!$success) {
                throw new Exception('Template no encontrado');
            }
            $result = [
                'success' => 1,
                'message' => 'Template eliminado correctamente',
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $result = [
                'success' => 0,
                'message' => $e->getMessage(),
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function exportTemplates(Request $request, Response $response) {
        try {
            $templates = (new TemplateModel())->getTemplates();
            if (!is_array($templates)) {
                throw new Exception('No se pudo obtener la lista de templates');
            }
            
            // Decodificar los campos JSON para exportar
            foreach ($templates as &$template) {
                if (isset($template['template_json'])) {
                    $template['template_json'] = json_decode($template['template_json'], true);
                }
                if (isset($template['example_json'])) {
                    $template['example_json'] = json_decode($template['example_json'], true);
                }
            }
            
            $exportData = [
                'version' => '1.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'total_templates' => count($templates),
                'templates' => $templates
            ];
            
            $filename = 'templates_export_' . date('Y-m-d_His') . '.json';
            $jsonContent = json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            
            $response->getBody()->write($jsonContent);
            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', strlen($jsonContent))
                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0');
                
        } catch (\Throwable $e) {
            $result = [
                'success' => 0,
                'message' => 'Error al exportar templates: ' . $e->getMessage(),
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    public function importTemplates(Request $request, Response $response) {
        try {
            // Verificar si se subió un archivo
            $uploadedFiles = $request->getUploadedFiles();
            
            if (isset($uploadedFiles['file'])) {
                // Modo: Subida de archivo
                $uploadedFile = $uploadedFiles['file'];
                
                if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                    throw new Exception('Error al subir el archivo');
                }
                
                // Verificar extensión
                $filename = $uploadedFile->getClientFilename();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($extension !== 'json') {
                    throw new Exception('Solo se permiten archivos .json');
                }
                
                // Leer contenido del archivo
                $fileContent = $uploadedFile->getStream()->getContents();
                $data = json_decode($fileContent, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('El archivo JSON es inválido: ' . json_last_error_msg());
                }
                
            } else {
                // Modo: JSON directo en el body (retrocompatibilidad)
                $rawBody = (string) $request->getBody();
                $request->getBody()->rewind();
                
                $data = $request->getParsedBody();
                if (empty($data) && !empty($rawBody)) {
                    $data = json_decode($rawBody, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('JSON inválido: ' . json_last_error_msg());
                    }
                }
            }
            
            if (empty($data) || !isset($data['templates'])) {
                throw new Exception('Formato inválido. Se requiere un objeto con la propiedad "templates"');
            }
            
            $templates = $data['templates'];
            if (!is_array($templates)) {
                throw new Exception('La propiedad "templates" debe ser un array');
            }
            
            $model = new TemplateModel();
            $imported = 0;
            $errors = [];
            $skipped = 0;
            
            foreach ($templates as $index => $template) {
                try {
                    $name = $template['name'] ?? '';
                    $caracteres = $template['caracteres'] ?? 48;
                    $paperWidthPx = $template['paperWidthPx'] ?? ($caracteres * 8);
                    $templateJson = $template['template_json'] ?? '';
                    $exampleJson = $template['example_json'] ?? '';
                    
                    if (empty($name) || empty($templateJson) || empty($exampleJson)) {
                        $errors[] = "Template #$index: Faltan campos requeridos";
                        $skipped++;
                        continue;
                    }
                    
                    // Convertir arrays a JSON si es necesario
                    if (is_array($templateJson)) {
                        $templateJson = json_encode($templateJson, JSON_UNESCAPED_UNICODE);
                    }
                    if (is_array($exampleJson)) {
                        $exampleJson = json_encode($exampleJson, JSON_UNESCAPED_UNICODE);
                    }
                    
                    // Verificar si ya existe un template con el mismo nombre
                    $existingTemplates = $model->getTemplates();
                    $exists = false;
                    foreach ($existingTemplates as $existing) {
                        if ($existing['name'] === $name) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if ($exists) {
                        $errors[] = "Template '$name': Ya existe, omitido";
                        $skipped++;
                        continue;
                    }
                    
                    // Insertar template
                    $model->insertTemplate($name, $caracteres, $paperWidthPx, $templateJson, $exampleJson);
                    $imported++;
                    
                } catch (\Throwable $e) {
                    $errors[] = "Template #$index: " . $e->getMessage();
                    $skipped++;
                }
            }
            
            $result = [
                'success' => 1,
                'message' => "Importación completada. Importados: $imported, Omitidos: $skipped",
                'data' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'total_processed' => count($templates),
                    'errors' => $errors
                ]
            ];
            
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Throwable $e) {
            $result = [
                'success' => 0,
                'message' => 'Error al importar templates: ' . $e->getMessage(),
                'data' => []
            ];
            $response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
}
?>