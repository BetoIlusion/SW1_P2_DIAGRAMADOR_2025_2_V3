<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SpringBootFlutterGeneratorService
{
    private $mermaidData;
    private $projectName;
    private $outputPath;
    private $parsedStructure;

    public function __construct($mermaidData, $projectName = "erp-inventario")
    {
        $this->mermaidData = $mermaidData;
        $this->projectName = $projectName;
        $this->outputPath = storage_path("app/generated-projects/{$projectName}");
        $this->parsedStructure = [];
    }

    /**
     * Método principal que genera el proyecto completo Spring Boot + Flutter
     */
    public function generateCompleteProject()
    {
        try {
            // 1. Parsear Mermaid a estructura de datos
            $this->parsedStructure = $this->parseMermaidToStructure($this->mermaidData);

            // 2. Limpiar directorio de salida antes de generar
            $this->cleanOutputDirectory();

            // 3. Crear estructura de directorios (SOLO spring-boot y front)
            $this->createProjectStructure();

            // 4. Generar Backend Spring Boot usando servicio separado
            $springBootGenerator = new SpringBootBackendGeneratorService(
                $this->parsedStructure,
                $this->projectName,
                $this->outputPath
            );
            $springBootResult = $springBootGenerator->generateBackend();

            if (!$springBootResult['success']) {
                throw new \Exception('Spring Boot generation failed: ' . $springBootResult['error']);
            }

            // 5. Generar Frontend Flutter usando servicio separado (ahora genera en /front)
            $flutterGenerator = new FlutterFrontendGeneratorService(
                $this->parsedStructure,
                $this->projectName,
                $this->outputPath
            );
            $flutterResult = $flutterGenerator->generateFrontend();

            if (!$flutterResult['success']) {
                throw new \Exception('Flutter generation failed: ' . $flutterResult['error']);
            }

            // 6. Generar archivo README con instrucciones
            $this->generateReadme();

            return [
                'success' => true,
                'project_path' => $this->outputPath,
                'spring_boot' => $springBootResult,
                'flutter' => $flutterResult,
                'parsed_structure' => $this->parsedStructure,
                'message' => 'Proyecto Spring Boot + Flutter generado exitosamente'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Limpiar directorio de salida para evitar archivos residuales
     */
    private function cleanOutputDirectory()
    {
        if (File::exists($this->outputPath)) {
            // Eliminar solo las carpetas flutter y front si existen, mantener otros archivos
            if (File::exists($this->outputPath . '/flutter')) {
                File::deleteDirectory($this->outputPath . '/flutter');
            }
            if (File::exists($this->outputPath . '/front')) {
                File::deleteDirectory($this->outputPath . '/front');
            }
        }
    }

    /**
     * Parsear diagrama Mermaid a estructura PHP
     */
    private function parseMermaidToStructure($mermaidData)
    {
        $classes = [];
        $relationships = [];

        $lines = explode("\n", $mermaidData);
        $currentClass = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Detectar definición de clase
            if (preg_match('/class\s+(\w+)\s*\{/', $line, $matches)) {
                $className = $matches[1];
                $currentClass = $className;
                $classes[$className] = [
                    'name' => $className,
                    'table_name' => Str::snake(Str::plural($className)),
                    'attributes' => [],
                    'methods' => []
                ];
                
                // 🔧 SOLUCIÓN PARA IDs DUPLICADOS: Agregar automáticamente campo ID si no existe
                $hasId = false;
                foreach ($classes[$className]['attributes'] as $attr) {
                    if (strtolower($attr['name']) === 'id') {
                        $hasId = true;
                        break;
                    }
                }
                
                if (!$hasId) {
                    $classes[$className]['attributes'][] = [
                        'name' => 'id',
                        'type' => 'Long',
                        'visibility' => '+',
                        'column_name' => 'id'
                    ];
                }
                continue;
            }

            // Detectar atributos (ej: +String nombre, +int edad)
            if (preg_match('/^([+#-])(\w+)\s+(\w+)/', $line, $matches) && $currentClass) {
                $visibility = $matches[1];
                $type = $matches[2];
                $name = $matches[3];

                // 🔧 SOLUCIÓN: Evitar duplicar el campo 'id'
                if (strtolower($name) === 'id') {
                    // Reemplazar el atributo id existente con este
                    foreach ($classes[$currentClass]['attributes'] as &$attr) {
                        if (strtolower($attr['name']) === 'id') {
                            $attr['type'] = $this->mapToJavaType($type);
                            $attr['visibility'] = $visibility;
                            break;
                        }
                    }
                } else {
                    $classes[$currentClass]['attributes'][] = [
                        'name' => $name,
                        'type' => $this->mapToJavaType($type),
                        'visibility' => $visibility,
                        'column_name' => Str::snake($name)
                    ];
                }
            }

            // Detectar métodos (ej: +void consultar())
            if (preg_match('/^([+#-])(\w+)\s+(\w+)\(\)/', $line, $matches) && $currentClass) {
                $visibility = $matches[1];
                $returnType = $matches[2];
                $name = $matches[3];

                $classes[$currentClass]['methods'][] = [
                    'name' => $name,
                    'return_type' => $this->mapToJavaType($returnType),
                    'visibility' => $visibility,
                    'parameters' => []
                ];
            }

            // Detectar relaciones de herencia (ej: Persona <|-- Alumno)
            if (preg_match('/(\w+)\s*<\|--\s*(\w+)/', $line, $matches)) {
                $parentClass = $matches[1];
                $childClass = $matches[2];

                $relationships[] = [
                    'type' => 'inheritance',
                    'parent' => $parentClass,
                    'child' => $childClass
                ];
            }

            // Detectar relaciones de asociación (ej: Producto --> Categoria)
            if (preg_match('/(\w+)\s*-->\s*(\w+)/', $line, $matches)) {
                $fromClass = $matches[1];
                $toClass = $matches[2];

                $relationships[] = [
                    'type' => 'association',
                    'from' => $fromClass,
                    'to' => $toClass
                ];
            }

            // Fin de clase
            if (strpos($line, '}') !== false) {
                $currentClass = null;
            }
        }

        return [
            'classes' => $classes,
            'relationships' => $relationships
        ];
    }

    /**
     * Mapear tipos UML a tipos Java
     */
    private function mapToJavaType($umlType)
    {
        $typeMap = [
            'String' => 'String',
            'int' => 'Long',        // 🔧 CAMBIO: Usar Long para IDs en lugar de Integer
            'integer' => 'Long',    // 🔧 CAMBIO: Usar Long para IDs
            'long' => 'Long',
            'double' => 'Double',
            'float' => 'Float',
            'boolean' => 'Boolean',
            'date' => 'LocalDate',
            'datetime' => 'LocalDateTime',
            'void' => 'void',
            'id' => 'Long',         // 🔧 NUEVO: Mapear específicamente 'id' a Long
        ];

        return $typeMap[strtolower($umlType)] ?? 'String';
    }

    /**
     * Crear estructura de directorios del proyecto
     */
    private function createProjectStructure()
    {
        // Directorio principal
        $directories = [
            $this->outputPath,
            
            // 🎯 SOLO spring-boot structure
            $this->outputPath . '/spring-boot',
            $this->outputPath . '/spring-boot/src/main/java/com/example/' . $this->projectName,
            $this->outputPath . '/spring-boot/src/main/java/com/example/' . $this->projectName . '/entity',
            $this->outputPath . '/spring-boot/src/main/java/com/example/' . $this->projectName . '/repository',
            $this->outputPath . '/spring-boot/src/main/java/com/example/' . $this->projectName . '/controller',
            $this->outputPath . '/spring-boot/src/main/java/com/example/' . $this->projectName . '/service',
            $this->outputPath . '/spring-boot/src/main/java/com/example/' . $this->projectName . '/dto',
            $this->outputPath . '/spring-boot/src/main/resources',
            $this->outputPath . '/spring-boot/src/test/java/com/example/' . $this->projectName,
            
            // 🎯 SOLO front structure - NADA de 'flutter'
            $this->outputPath . '/front',
            $this->outputPath . '/front/lib',
            $this->outputPath . '/front/lib/models',
            $this->outputPath . '/front/lib/screens',
            $this->outputPath . '/front/lib/services',
            $this->outputPath . '/front/lib/widgets',
            $this->outputPath . '/front/lib/config',
            $this->outputPath . '/front/lib/navigation',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
    }

    /**
     * Generar archivo README con instrucciones
     */
    private function generateReadme()
    {
        $content = '# ' . Str::studly($this->projectName) . ' ERP

Proyecto generado automáticamente desde diagrama UML.

## Estructura del Proyecto
' . $this->projectName . '/
├── spring-boot/    # Backend Spring Boot
├── front/          # Frontend Flutter  
├── run-spring-boot.bat
├── run-spring-boot.sh  
├── run-flutter.bat
└── run-flutter.sh

## URLs del Sistema
- **Backend API**: http://localhost:8080
- **Frontend**: http://localhost:3000

## Entidades Generadas
';

        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            $content .= '- **' . Str::studly($className) . '**: ' . count($classData['attributes']) . " atributos\n";
        }

        $content .= '

## Notas Técnicas
- IDs automáticamente gestionados como Long
- Base de datos H2 en memoria
- CRUD completo para cada entidad';

        File::put($this->outputPath . '/README.md', $content);
    }
}