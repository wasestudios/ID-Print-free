<?php
namespace App\Controllers;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\TemplateModel;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\CapabilityProfile;

use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Builder\Builder;

use Exception;


class PrinterController {
    /**
     * Imprime múltiples tickets según el JSON recibido
     * POST /print
     * Body: array de objetos { templateId, printerName, data }
     */
    public function print(Request $request, Response $response, $args = []) {
        $jobs = $request->getParsedBody();
        if (!is_array($jobs)) {
            $rawBody = (string) $request->getBody();
            $jobs = json_decode($rawBody, true);
        }
        if (!is_array($jobs)) {
            $response->getBody()->write(json_encode([
                'success' => 0,
                'message' => 'El cuerpo debe ser un array JSON válido.'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $results = [];
        foreach ($jobs as $job) {
            $templateId = $job['templateId'] ?? null;
            $printerName = $job['printerName'] ?? null;
            $data = $job['data'] ?? [];

            if (!$templateId) {
                $results[] = [
                    'success' => 0,
                    'message' => 'ID de template es requerido',
                    'template_id' => $templateId,
                    'printer_name' => $printerName
                ];
                continue;
            }
            if (!$printerName) {
                $results[] = [
                    'success' => 0,
                    'message' => 'Nombre de impresora es requerido',
                    'template_id' => $templateId,
                    'printer_name' => $printerName
                ];
                continue;
            }

            try {
                $model = new TemplateModel();
                $template = $model->getTemplateById($templateId);
                
                if (!$template) {
                    $response->getBody()->write(json_encode(['success' => 0, 'message' => 'Template no encontrado']));
                    return $response->withHeader('Content-Type', 'application/json');
                }

                $templateJson = json_decode($template['template_json'], true);
                $exampleJson = json_decode($template['example_json'], true);
                $caracteres = $template['caracteres'] ?? 48;
                $paperWidth = $caracteres * 8; // Calcula el ancho real en píxeles

                $connector = new WindowsPrintConnector($printerName);
                //$profile = CapabilityProfile::load("simple");
                $printer = new Printer($connector);

                // Inicializar impresora
                $printer->initialize();
                $printer->setJustification(Printer::JUSTIFY_LEFT);

                

                // Procesar cada elemento del template
                foreach ($templateJson as $item) {
                    $type = $item['type'] ?? 'text';
                    $align = $item['align'] ?? 'left';
                    $fontSize = $item['fontSize'] ?? '1x1';
                    $columns = $item['columns'] ?? [];

                    $textType = $item['textType'] ?? 'static';
                    $field = $item['field'] ?? '';
                    $barcodeFormat = $item['formatBarcode'] ?? 'CODE128';
                    $barcodeSizeMap = [
                        '1x' => ['width' => 1.0, 'height' => 30, 'fontSize' => 11],
                        '2x' => ['width' => 1.8, 'height' => 45, 'fontSize' => 14],
                        '3x' => ['width' => 2.6, 'height' => 60, 'fontSize' => 17],
                        '4x' => ['width' => 3.4, 'height' => 80, 'fontSize' => 20],
                        '5x' => ['width' => 4.2, 'height' => 100, 'fontSize' => 23]
                    ];
                    $barcodeSize = $barcodeSizeMap[$item['size'] ?? '1x'];

                    // Configurar alineación
                    switch ($align) {
                        case 'center': 
                            $printer->setJustification(Printer::JUSTIFY_CENTER); 
                            break;
                        case 'right': 
                            $printer->setJustification(Printer::JUSTIFY_RIGHT); 
                            break;
                        default: 
                            $printer->setJustification(Printer::JUSTIFY_LEFT); 
                            break;
                    }

                    // Configurar tamaño de fuente
                    $fontSizeMap = [
                        '10px'  => [1, 1],
                        '12px' => [2, 1],
                        '16px' => [1, 2],
                        '24px' => [2, 2],
                        '32px' => [4, 4]
                    ];
                    $fontSizeFrontend = $item['fontSize'] ?? '8px';
                    $size = $fontSizeMap[$fontSizeFrontend] ?? [1, 1];
                    $printer->setTextSize($size[0], $size[1]);

                    // Configurar alineación
                    switch ($align) {
                        case 'center': 
                            $printer->setJustification(Printer::JUSTIFY_CENTER); 
                            break;
                        case 'right': 
                            $printer->setJustification(Printer::JUSTIFY_RIGHT); 
                            break;
                        default: 
                            $printer->setJustification(Printer::JUSTIFY_LEFT); 
                            break;
                    }

                    // Configurar tamaño de fuente
                    $fontSizeMap = [
                        '11px' => [1, 1],
                        '12px' => [2, 1],
                        '16px' => [1, 2],
                        '24px' => [2, 2],
                        '32px' => [4, 4]
                    ];
                    $fontSizeFrontend = $item['fontSize'] ?? '8px';
                    $size = $fontSizeMap[$fontSizeFrontend] ?? [1, 1];
                    $printer->setTextSize($size[0], $size[1]);

                    // Procesar según tipo de elemento                
                    switch ($type) {
                        case 'text':
                        /* $text = $item['text'] ?? '';
                            if (is_array($text)) {
                                $text = json_encode($text, JSON_UNESCAPED_UNICODE);
                            }
                            $fontWeight = $item['fontWeight'] ?? 'normal';
                            $fontUnderline = $item['fontUnderline'] ?? 'none';
                            $printer->setEmphasis($fontWeight === 'bold');
                            $printer->setUnderline($fontUnderline === 'underline');
                            $printer->text($text . "\n");
                            // Restablecer estilos
                            $printer->setEmphasis(false);
                            $printer->setUnderline(false);*/
                            $fontWeight = $item['fontWeight'] ?? 'normal';
                            $fontUnderline = $item['fontUnderline'] ?? 'none';
                            $printer->setEmphasis($fontWeight === 'bold');
                            $printer->setUnderline($fontUnderline === 'underline');
                            if (isset($item['leftText']) && isset($item['rightText'])) {
                                // Imprimir en dos columnas
                                $this->printTwoColumnLine($printer, $item['leftText'], $item['rightText']);
                            } else {
                                $text = $item['text'] ?? '';
                                if (is_array($text)) {
                                    $text = json_encode($text, JSON_UNESCAPED_UNICODE);
                                }
                                $printer->text($text . "\n");
                            }
                            $printer->setEmphasis(false);
                            $printer->setUnderline(false);
                            break;

                        case 'field':
                            /*$field = $item['field'] ?? '';
                            $textBefore = $item['textBefore'] ?? '';
                            $textAfter = $item['textAfter'] ?? '';
                            $value = $this->getFieldValue($exampleJson, $field);
                            $fontWeight = $item['fontWeight'] ?? 'normal';
                            $fontUnderline = $item['fontUnderline'] ?? 'none';
                            $printer->setEmphasis($fontWeight === 'bold');
                            $printer->setUnderline($fontUnderline === 'underline');
                            if (!empty($columns) && is_array($value)) {
                                $this->printTable($printer, $columns, $value);
                            } else {
                                if (is_array($value)) {
                                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                                }
                                $printer->text($textBefore . $value . $textAfter . "\n");
                            }
                            // Restablecer estilos
                            $printer->setEmphasis(false);
                            $printer->setUnderline(false);*/
                            $fontWeight = $item['fontWeight'] ?? 'normal';
                            $fontUnderline = $item['fontUnderline'] ?? 'none';
                            $printer->setEmphasis($fontWeight === 'bold');
                            $printer->setUnderline($fontUnderline === 'underline');
                            if (isset($item['leftField']) && isset($item['rightField'])) {
                                // Obtener valores de los campos
                                $leftValue = $this->getFieldValue($exampleJson, $item['leftField']);
                                $rightValue = $this->getFieldValue($exampleJson, $item['rightField']);
                                $this->printTwoColumnLine($printer, $leftValue, $rightValue);
                            } else {
                                    $field = $item['field'] ?? '';
                                    $textBefore = $item['textBefore'] ?? '';
                                    $textAfter = $item['textAfter'] ?? '';
                                    $value = $this->getFieldValue($exampleJson, $field);
                                    if (!empty($columns) && is_array($value)) {
                                        $this->printTable($printer, $columns, $value);
                                    } else {
                                        if (is_array($value)) {
                                            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                                        }
                                        $printer->text($textBefore . $value . $textAfter . "\n");
                                    }
                                }
                                $printer->setEmphasis(false);
                                $printer->setUnderline(false);
                            break;

                        case 'line':
                            $printer->text(str_repeat('-', $caracteres) . "\n");
                            break;

                        case 'doubleline':
                            $printer->text(str_repeat('=', $caracteres) . "\n");
                            break;


                        case 'feed':
                            $lines = $item['lines'] ?? 1;
                            $printer->feed($lines);
                            break;

                        case 'newline':
                            $printer->feed(1);
                            break;
                        }
                }

                // Finalizar impresión
                $printer->feed(3);
                // buscar en templateJson en la columna type cut, si existe habilitar el corte
                if (in_array('cut', array_column($templateJson, 'type'))) {
                    $printer->cut();
                }
                
                $printer->close();

                $results[] = [
                    'success' => 1,
                    'message' => 'Ticket impreso correctamente en ' . $printerName,
                    'template_id' => $templateId,
                    'printer_name' => $printerName,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => 0,
                    'message' => 'Error al imprimir: ' . $e->getMessage(),
                    'template_id' => $templateId,
                    'printer_name' => $printerName,
                    'error_type' => 'general'
                ];
            }
        }

        $response->getBody()->write(json_encode($results));
        return $response->withHeader('Content-Type', 'application/json');
    }


    public function list(Request $request, Response $response, $args = []) {
        $printers = [];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Obtener detalles adicionales de las impresoras en Windows
            exec('wmic printer get Name,Shared,WorkOffline,Default,Status,Network,Availability /format:csv', $output);
            $headers = [];
            foreach ($output as $i => $line) {
                $line = trim($line);
                if ($line === '' || stripos($line, 'Node,Name') !== false) continue;
                if (empty($headers) && strpos($line, ',') !== false) {
                    $headers = array_map('trim', explode(',', $line));
                    continue;
                }
                if ($line && strpos($line, ',') !== false) {
                    $cols = array_map('trim', explode(',', $line));
                    // Si hay más columnas que headers, ajusta
                    if (count($cols) > count($headers)) {
                        $cols = array_slice($cols, -count($headers));
                    }
                    $printer = [];
                    foreach ($headers as $idx => $header) {
                        $printer[$header] = $cols[$idx] ?? null;
                    }
                    if (!empty($printer['Name'])) {
                        $printers[] = [
                            'name'        => $printer['Name'],
                            'shared'      => $printer['Shared'],
                            'work_offline'=> $printer['WorkOffline'],
                            'default'     => $printer['Default'],
                            'status'      => $printer['Status'],
                            'network'     => $printer['Network'],
                            'availability'=> $printer['Availability'],
                        ];
                    }
                }
            }
        } else {
            // Linux: obtener nombre y estado de impresoras
            exec('lpstat -p', $output);
            foreach ($output as $line) {
                if (preg_match('/^printer\s+(\S+)\s+(.*)$/', $line, $matches)) {
                    $printers[] = [
                        'name'   => $matches[1],
                        'status' => $matches[2],
                    ];
                }
            }
        }
        $payload = json_encode(['printers' => $printers], JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function printTemplateTest(Request $request, Response $response, $args) {

        $id = $args['id'] ?? null;
        $printerName = $request->getParsedBody()['printerName'] ?? null;

        if (!$id) {
           throw new Exception('ID de template es requerido');
        }

        if(!$printerName) {
            throw new Exception('Nombre de impresora es requerido');
        }
        
        try {
            $model = new TemplateModel();
            $template = $model->getTemplateById($id);
            
            if (!$template) {
                $response->getBody()->write(json_encode(['success' => 0, 'message' => 'Template no encontrado']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $templateJson = json_decode($template['template_json'], true);
            $exampleJson = json_decode($template['example_json'], true);
            $caracteres = $template['caracteres'] ?? 48;
            $paperWidth = $caracteres * 8; // Calcula el ancho real en píxeles

            $connector = new WindowsPrintConnector($printerName);
            //$profile = CapabilityProfile::load("simple");
            $printer = new Printer($connector);

            // Inicializar impresora
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            // Procesar cada elemento del template
            foreach ($templateJson as $item) {
                $type = $item['type'] ?? 'text';
                $align = $item['align'] ?? 'left';
                $fontSize = $item['fontSize'] ?? '1x1';
                $columns = $item['columns'] ?? [];

                $textType = $item['textType'] ?? 'static';
                $field = $item['field'] ?? '';
                $barcodeFormat = $item['formatBarcode'] ?? 'CODE128';
                $barcodeSizeMap = [
                    '1x' => ['width' => 1.0, 'height' => 30, 'fontSize' => 11],
                    '2x' => ['width' => 1.8, 'height' => 45, 'fontSize' => 14],
                    '3x' => ['width' => 2.6, 'height' => 60, 'fontSize' => 17],
                    '4x' => ['width' => 3.4, 'height' => 80, 'fontSize' => 20],
                    '5x' => ['width' => 4.2, 'height' => 100, 'fontSize' => 23]
                ];
                $barcodeSize = $barcodeSizeMap[$item['size'] ?? '1x'];
                
                // Configurar alineación
                switch ($align) {
                    case 'center': 
                        $printer->setJustification(Printer::JUSTIFY_CENTER); 
                        break;
                    case 'right': 
                        $printer->setJustification(Printer::JUSTIFY_RIGHT); 
                        break;
                    default: 
                        $printer->setJustification(Printer::JUSTIFY_LEFT); 
                        break;
                }

                // Configurar tamaño de fuente
                $fontSizeMap = [
                    '11px' => [1, 1],
                    '12px' => [2, 1],
                    '16px' => [1, 2],
                    '24px' => [2, 2],
                    '32px' => [4, 4]
                ];
                $fontSizeFrontend = $item['fontSize'] ?? '8px';
                $size = $fontSizeMap[$fontSizeFrontend] ?? [1, 1];
                $printer->setTextSize($size[0], $size[1]);

                // Procesar según tipo de elemento
                switch ($type) {
                    case 'text':
                       /* $text = $item['text'] ?? '';
                        if (is_array($text)) {
                            $text = json_encode($text, JSON_UNESCAPED_UNICODE);
                        }
                        $fontWeight = $item['fontWeight'] ?? 'normal';
                        $fontUnderline = $item['fontUnderline'] ?? 'none';
                        $printer->setEmphasis($fontWeight === 'bold');
                        $printer->setUnderline($fontUnderline === 'underline');
                        $printer->text($text . "\n");
                        // Restablecer estilos
                        $printer->setEmphasis(false);
                        $printer->setUnderline(false);*/
                         $fontWeight = $item['fontWeight'] ?? 'normal';
                        $fontUnderline = $item['fontUnderline'] ?? 'none';
                        $printer->setEmphasis($fontWeight === 'bold');
                        $printer->setUnderline($fontUnderline === 'underline');
                        if (isset($item['leftText']) && isset($item['rightText'])) {
                            // Imprimir en dos columnas
                            $this->printTwoColumnLine($printer, $item['leftText'], $item['rightText']);
                        } else {
                            $text = $item['text'] ?? '';
                            if (is_array($text)) {
                                $text = json_encode($text, JSON_UNESCAPED_UNICODE);
                            }
                            $printer->text($text . "\n");
                        }
                        $printer->setEmphasis(false);
                        $printer->setUnderline(false);
                        break;

                    case 'field':
                        /*$field = $item['field'] ?? '';
                        $textBefore = $item['textBefore'] ?? '';
                        $textAfter = $item['textAfter'] ?? '';
                        $value = $this->getFieldValue($exampleJson, $field);
                        $fontWeight = $item['fontWeight'] ?? 'normal';
                        $fontUnderline = $item['fontUnderline'] ?? 'none';
                        $printer->setEmphasis($fontWeight === 'bold');
                        $printer->setUnderline($fontUnderline === 'underline');
                        if (!empty($columns) && is_array($value)) {
                            $this->printTable($printer, $columns, $value);
                        } else {
                            if (is_array($value)) {
                                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                            }
                            $printer->text($textBefore . $value . $textAfter . "\n");
                        }
                        // Restablecer estilos
                        $printer->setEmphasis(false);
                        $printer->setUnderline(false);*/
                        $fontWeight = $item['fontWeight'] ?? 'normal';
                        $fontUnderline = $item['fontUnderline'] ?? 'none';
                        $printer->setEmphasis($fontWeight === 'bold');
                        $printer->setUnderline($fontUnderline === 'underline');
                        if (isset($item['leftField']) && isset($item['rightField'])) {
                            // Obtener valores de los campos
                            $leftValue = $this->getFieldValue($exampleJson, $item['leftField']);
                            $rightValue = $this->getFieldValue($exampleJson, $item['rightField']);
                            $this->printTwoColumnLine($printer, $leftValue, $rightValue);
                        } else {
                                $field = $item['field'] ?? '';
                                $textBefore = $item['textBefore'] ?? '';
                                $textAfter = $item['textAfter'] ?? '';
                                $value = $this->getFieldValue($exampleJson, $field);
                                if (!empty($columns) && is_array($value)) {
                                    $this->printTable($printer, $columns, $value);
                                } else {
                                    if (is_array($value)) {
                                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                                    }
                                    $printer->text($textBefore . $value . $textAfter . "\n");
                                }
                            }
                            $printer->setEmphasis(false);
                            $printer->setUnderline(false);
                        break;

                    case 'line':
                        $printer->text(str_repeat('-', $caracteres) . "\n");
                        break;

                    case 'doubleline':
                        $printer->text(str_repeat('=', $caracteres) . "\n");
                        break;

                    case 'feed':
                        $lines = $item['lines'] ?? 1;
                        $printer->feed($lines);
                        break;

                    case 'newline':
                        $printer->feed(1);
                        break;
                    }
            }

            // Finalizar impresión
            $printer->feed(3);
            // buscar en templateJson en la columna type cut, si existe habilitar el corte
            if (in_array('cut', array_column($templateJson, 'type'))) {
               $printer->cut();
            }
            
            $printer->close();

            $response->getBody()->write(json_encode([
                'success' => 1, 
                'message' => 'Ticket impreso correctamente en ' . $printerName,
                'template_id' => $id,
                'timestamp' => date('Y-m-d H:i:s')
            ]));

        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => 0, 
                'message' => 'Error de conexión con impresora: ' . $e->getMessage(),
                'error_type' => 'printer_connection'
            ]));
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => 0, 
                'message' => 'Error al imprimir: ' . $e->getMessage(),
                'error_type' => 'general'
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Imprime una línea con dos columnas (izquierda y derecha) ajustadas al ancho total
     */

    private function printTwoColumnLine($printer, $left, $right, $totalWidth = 48) {
        $maxLeft = $totalWidth - strlen($right) - 1;
        $left = mb_substr($left, 0, $maxLeft);
        $right = mb_substr($right, 0, $totalWidth - strlen($left) - 1);
        $spaces = $totalWidth - strlen($left) - strlen($right);
        $line = $left . str_repeat(' ', $spaces) . $right . "\n";
        $printer->text($line);
    }

    /**
     * Obtiene el valor de un campo, soportando notación punto para campos anidados
     */
    private function getFieldValue($data, $field) {
        if (strpos($field, '.') === false) {
            return $data[$field] ?? '';
        }
        
        $parts = explode('.', $field);
        $value = $data;
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return '';
            }
        }
        return $value;
    }

    /**
     * Imprime una tabla con columnas específicas
     */
    private function printTable($printer, $columns, $data) {
        // Calcular ancho disponible (48 caracteres para papel 80mm)
        $totalWidth = 48;
        $colCount = count($columns);
        $colWidth = floor(($totalWidth - $colCount + 1) / $colCount); // -1 por separadores
        
        // Imprimir encabezados
        $header = '';
        foreach ($columns as $i => $col) {
            $header .= str_pad(substr($col, 0, $colWidth), $colWidth);
            if ($i < $colCount - 1) $header .= ' ';
        }
        $printer->setEmphasis(true);
        $printer->text($header . "\n");
        $printer->setEmphasis(false);
        
        // Línea separadora
        $printer->text(str_repeat('-', $totalWidth) . "\n");
        
        // Imprimir filas de datos
        foreach ($data as $row) {
            $line = '';
            foreach ($columns as $i => $col) {
                $cellValue = isset($row[$col]) ? $row[$col] : '';
                $line .= str_pad(substr($cellValue, 0, $colWidth), $colWidth);
                if ($i < $colCount - 1) $line .= ' ';
            }
            $printer->text($line . "\n");
        }
    }
    /**
     * Traduce el formato de código de barras del frontend al formato de Mike42
     */
    public static function translateBarcodeFormat($frontendFormat) {
        $map = [
            'CODE128' => 'BARCODE_CODE128',
            'CODE39'  => 'BARCODE_CODE39',
            'EAN13'   => 'BARCODE_JAN13',
            'EAN8'    => 'BARCODE_JAN8',
            'UPC'     => 'BARCODE_UPCA',
            'ITF'     => 'BARCODE_ITF'
        ];
        return $map[$frontendFormat] ?? null;
    }
}

 // Descomentar la siguiente línea si tu impresora soporta impresión directa de QR
// $printer->qrCode($qrText, Printer::QR_ECLEVEL_L, $sizeQR);
//$printer->feed(1);