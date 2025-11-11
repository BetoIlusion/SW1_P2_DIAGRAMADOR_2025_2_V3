<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FlutterFrontendGeneratorService
{
    private $projectName;
    private $parsedStructure;
    private $outputPath;
    private $springBootUrl;

    public function __construct($parsedStructure, $projectName, $outputPath, $springBootUrl = "http://localhost:8080")
    {
        $this->parsedStructure = $parsedStructure;
        $this->projectName = $projectName;
        $this->outputPath = $outputPath . '/front';
        $this->springBootUrl = $springBootUrl;
    }

    /**
     * Método principal que genera el frontend Flutter completo
     */
    public function generateFrontend()
    {
        try {
            // 1. Generar archivos de configuración base
            $this->generateConfigurationFiles();
            
            // 2. Generar modelos Dart
            $models = $this->generateModels();
            
            // 3. Generar servicios API
            $services = $this->generateServices();
            
            // 4. Generar pantallas CRUD
            $screens = $this->generateScreens();
            
            // 5. Generar widgets comunes
            $widgets = $this->generateWidgets();
            
            // 6. Generar navegación
            $navigation = $this->generateNavigation();
            
            // 7. Generar script de ejecución
            $runScripts = $this->generateRunScripts();

            // 8. Generar test corregido
            $this->generateWidgetTest();

            return [
                'success' => true,
                'project_path' => $this->outputPath,
                'models_count' => count($models),
                'services_count' => count($services),
                'screens_count' => count($screens),
                'files_generated' => [
                    'pubspec' => $this->outputPath . '/pubspec.yaml',
                    'main' => $this->outputPath . '/lib/main.dart',
                    'models' => $models,
                    'services' => $services,
                    'screens' => $screens,
                    'widgets' => $widgets,
                    'navigation' => $navigation,
                    'run_scripts' => $runScripts
                ],
                'message' => 'Frontend Flutter generado exitosamente'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generar archivos de configuración base
     */
    private function generateConfigurationFiles()
    {
        $this->generatePubspecYaml();
        $this->generateMainDart();
        $this->generateEnvironmentConfig();
    }

    /**
     * Generar pubspec.yaml
     */
    private function generatePubspecYaml()
    {
        $content = 'name: ' . Str::snake($this->projectName) . '
description: "ERP Flutter app generated from UML diagram"
publish_to: "none"

version: 1.0.0+1

environment:
  sdk: ">=3.0.0 <4.0.0"

dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0
  provider: ^6.1.1
  shared_preferences: ^2.2.2

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^3.0.0

flutter:
  uses-material-design: true

  assets:
    - assets/';

        File::ensureDirectoryExists($this->outputPath);
        File::put($this->outputPath . '/pubspec.yaml', $content);
    }

    /**
     * Generar main.dart
     */
    private function generateMainDart()
    {
        $content = 'import \'package:flutter/material.dart\';
import \'app.dart\';

void main() {
  runApp(const ' . Str::studly($this->projectName) . 'App());
}';

        File::ensureDirectoryExists($this->outputPath . '/lib');
        File::put($this->outputPath . '/lib/main.dart', $content);
    }

    /**
     * Generar configuración de entorno
     */
    private function generateEnvironmentConfig()
    {
        $content = 'class AppConfig {
  static const String baseUrl = "' . $this->springBootUrl . '";
  
  static const Map<String, String> headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
  };
}';

        File::ensureDirectoryExists($this->outputPath . '/lib/config');
        File::put($this->outputPath . '/lib/config/app_config.dart', $content);
    }

    /**
     * Generar modelos Dart
     */
    private function generateModels()
    {
        $models = [];
        
        File::ensureDirectoryExists($this->outputPath . '/lib/models');
        
        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            $modelContent = $this->generateModelContent($className, $classData);
            $fileName = Str::snake($className) . '_model.dart';
            $filePath = $this->outputPath . '/lib/models/' . $fileName;
            File::put($filePath, $modelContent);
            $models[] = $filePath;
        }
        
        return $models;
    }

    /**
     * Contenido del modelo Dart individual
     */
    private function generateModelContent($className, $classData)
    {
        $studlyName = Str::studly($className);
        
        // Generar campos del modelo
        $fields = "";
        $constructorParams = "";
        $fromJsonLines = [];
        $toJsonLines = [];
        
        foreach ($classData['attributes'] as $attr) {
            $dartType = $this->mapToDartType($attr['type']);
            $fieldName = Str::camel($attr['name']);
            
            // Campos
            $fields .= "  final $dartType $fieldName;\n";
            
            // Parámetros del constructor - todos required
            $constructorParams .= "    required this.$fieldName,\n";
            
            // FromJson - usar método de conversión segura
            $jsonConversion = $this->getSafeJsonConversion($dartType, $fieldName);
            $fromJsonLines[] = "      $fieldName: $jsonConversion";
            
            // ToJson - manejar tipos especiales
            $toJsonConversion = $this->getToJsonConversion($dartType, $fieldName);
            $toJsonLines[] = "      '$fieldName': $toJsonConversion";
        }
        
        $fromJson = implode(",\n", $fromJsonLines);
        $toJson = implode(",\n", $toJsonLines);
        
        $copyWithParams = [];
        $copyWithImpl = [];
        
        foreach ($classData['attributes'] as $attr) {
            $dartType = $this->mapToDartType($attr['type']);
            $fieldName = Str::camel($attr['name']);
            // En copyWith los parámetros son opcionales
            $copyWithParams[] = "$dartType? $fieldName";
            $copyWithImpl[] = "$fieldName: $fieldName ?? this.$fieldName";
        }
        
        $copyWithParamsStr = implode(",\n    ", $copyWithParams);
        $copyWithImplStr = implode(",\n      ", $copyWithImpl);

        $content = 'class ' . $studlyName . ' {
' . $fields . '
  ' . $studlyName . '({
' . $constructorParams . '  });

  factory ' . $studlyName . '.fromJson(Map<String, dynamic> json) {
    return ' . $studlyName . '(
' . $fromJson . '
    );
  }

  Map<String, dynamic> toJson() {
    return {
' . $toJson . '
    };
  }

  ' . $studlyName . ' copyWith({
    ' . $copyWithParamsStr . '
  }) {
    return ' . $studlyName . '(
      ' . $copyWithImplStr . '
    );
  }

  // Helper methods for safe parsing
  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is String) return int.tryParse(value) ?? 0;
    if (value is double) return value.toInt();
    return 0;
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is String) return double.tryParse(value) ?? 0.0;
    if (value is int) return value.toDouble();
    return 0.0;
  }
}';

        return $content;
    }

    /**
     * Obtener conversión segura para JSON
     */
    private function getSafeJsonConversion($dartType, $fieldName)
    {
        switch ($dartType) {
            case 'int':
                return "_parseInt(json['$fieldName'])";
            case 'double':
                return "_parseDouble(json['$fieldName'])";
            case 'bool':
                return "json['$fieldName'] as bool";
            case 'DateTime':
                return "json['$fieldName'] != null ? DateTime.parse(json['$fieldName'] as String) : DateTime.now()";
            default:
                return "json['$fieldName'] as String";
        }
    }

    /**
     * Obtener conversión para toJson
     */
    private function getToJsonConversion($dartType, $fieldName)
    {
        switch ($dartType) {
            case 'DateTime':
                return "$fieldName.toIso8601String()";
            default:
                return $fieldName;
        }
    }

    /**
     * Mapear tipos Java a tipos Dart
     */
    private function mapToDartType($javaType)
    {
        $typeMap = [
            'String' => 'String',
            'Integer' => 'int',
            'Long' => 'int',
            'Double' => 'double',
            'Float' => 'double',
            'Boolean' => 'bool',
            'LocalDate' => 'DateTime',
            'LocalDateTime' => 'DateTime',
            'int' => 'int',
            'double' => 'double',
            'bool' => 'bool',
            'DateTime' => 'DateTime',
        ];
        
        return $typeMap[$javaType] ?? 'String';
    }

    /**
     * Generar servicios API
     */
    private function generateServices()
    {
        $services = [];
        
        File::ensureDirectoryExists($this->outputPath . '/lib/services');
        
        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            $serviceContent = $this->generateServiceContent($className);
            $fileName = Str::snake($className) . '_service.dart';
            $filePath = $this->outputPath . '/lib/services/' . $fileName;
            File::put($filePath, $serviceContent);
            $services[] = $filePath;
        }
        
        return $services;
    }

    private function generateServiceContent($className)
    {
        $studlyName = Str::studly($className);
        $camelName = Str::camel($className);
        $tableName = Str::snake(Str::plural($className));

        $content = 'import \'dart:convert\';
import \'package:http/http.dart\' as http;
import \'../config/app_config.dart\';
import \'../models/' . Str::snake($className) . '_model.dart\';

class ' . $studlyName . 'Service {
  static const String basePath = "api/' . $tableName . '";

  static Future<List<' . $studlyName . '>> getAll() async {
    final response = await http.get(
      Uri.parse("${AppConfig.baseUrl}/$basePath"),
      headers: AppConfig.headers,
    );

    if (response.statusCode == 200) {
      final List<dynamic> jsonList = json.decode(response.body);
      return jsonList.map((json) => ' . $studlyName . '.fromJson(json)).toList();
    } else {
      throw Exception("Failed to load ' . $studlyName . ' list");
    }
  }

  static Future<' . $studlyName . '> getById(int id) async {
    final response = await http.get(
      Uri.parse("${AppConfig.baseUrl}/$basePath/\$id"),
      headers: AppConfig.headers,
    );

    if (response.statusCode == 200) {
      return ' . $studlyName . '.fromJson(json.decode(response.body));
    } else {
      throw Exception("Failed to load ' . $studlyName . '");
    }
  }

  static Future<' . $studlyName . '> create(' . $studlyName . ' ' . $camelName . ') async {
    final response = await http.post(
      Uri.parse("${AppConfig.baseUrl}/$basePath"),
      headers: AppConfig.headers,
      body: json.encode(' . $camelName . '.toJson()),
    );

    if (response.statusCode == 201 || response.statusCode == 200) {
      return ' . $studlyName . '.fromJson(json.decode(response.body));
    } else {
      throw Exception("Failed to create ' . $studlyName . '. Status code: ${response.statusCode}");
    }
  }

  static Future<' . $studlyName . '> update(int id, ' . $studlyName . ' ' . $camelName . ') async {
    final response = await http.put(
      Uri.parse("${AppConfig.baseUrl}/$basePath/\$id"),
      headers: AppConfig.headers,
      body: json.encode(' . $camelName . '.toJson()),
    );

    if (response.statusCode == 200) {
      return ' . $studlyName . '.fromJson(json.decode(response.body));
    } else {
      throw Exception("Failed to update ' . $studlyName . '");
    }
  }

  static Future<void> delete(int id) async {
    final response = await http.delete(
      Uri.parse("${AppConfig.baseUrl}/$basePath/\$id"),
      headers: AppConfig.headers,
    );

    if (response.statusCode != 200 && response.statusCode != 204) {
      throw Exception("Failed to delete ' . $studlyName . '");
    }
  }
}';

        return $content;
    }

    /**
     * Generar pantallas CRUD
     */
    private function generateScreens()
    {
        $screens = [];
        
        File::ensureDirectoryExists($this->outputPath . '/lib/screens');
        
        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            // Pantalla de lista
            $listScreenContent = $this->generateListScreenContent($className, $classData);
            $listFileName = Str::snake($className) . '_list_screen.dart';
            $listFilePath = $this->outputPath . '/lib/screens/' . $listFileName;
            File::put($listFilePath, $listScreenContent);
            $screens[] = $listFilePath;
            
            // Pantalla de formulario
            $formScreenContent = $this->generateFormScreenContent($className, $classData);
            $formFileName = Str::snake($className) . '_form_screen.dart';
            $formFilePath = $this->outputPath . '/lib/screens/' . $formFileName;
            File::put($formFilePath, $formScreenContent);
            $screens[] = $formFilePath;
        }
        
        // Pantalla principal/dashboard
        $dashboardContent = $this->generateDashboardScreen();
        $dashboardFilePath = $this->outputPath . '/lib/screens/dashboard_screen.dart';
        File::put($dashboardFilePath, $dashboardContent);
        $screens[] = $dashboardFilePath;
        
        return $screens;
    }

    private function generateListScreenContent($className, $classData)
    {
        $studlyName = Str::studly($className);
        $camelName = Str::camel($className);
        $primaryField = $this->getPrimaryField($classData);

        $content = 'import \'package:flutter/material.dart\';
import \'../models/' . Str::snake($className) . '_model.dart\';
import \'../services/' . Str::snake($className) . '_service.dart\';
import \'./' . Str::snake($className) . '_form_screen.dart\';

class ' . $studlyName . 'ListScreen extends StatefulWidget {
  const ' . $studlyName . 'ListScreen({super.key});

  @override
  State<' . $studlyName . 'ListScreen> createState() => _' . $studlyName . 'ListScreenState();
}

class _' . $studlyName . 'ListScreenState extends State<' . $studlyName . 'ListScreen> {
  List<' . $studlyName . '> ' . $camelName . 's = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    _load' . $studlyName . 's();
  }

  Future<void> _load' . $studlyName . 's() async {
    try {
      final loaded' . $studlyName . 's = await ' . $studlyName . 'Service.getAll();
      setState(() {
        ' . $camelName . 's = loaded' . $studlyName . 's;
        isLoading = false;
      });
    } catch (e) {
      setState(() {
        isLoading = false;
      });
      _showErrorSnackBar("Error loading ' . $studlyName . ': \$e");
    }
  }

  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
      ),
    );
  }

  void _navigateToFormScreen(' . $studlyName . '? ' . $camelName . ') {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ' . $studlyName . 'FormScreen(
          ' . $camelName . ': ' . $camelName . ',
          onSaved: _load' . $studlyName . 's,
        ),
      ),
    );
  }

  Future<void> _delete' . $studlyName . '(int id) async {
    try {
      await ' . $studlyName . 'Service.delete(id);
      _load' . $studlyName . 's();
      _showSuccessSnackBar("' . $studlyName . ' deleted successfully");
    } catch (e) {
      _showErrorSnackBar("Error deleting ' . $studlyName . ': \$e");
    }
  }

  void _showSuccessSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
      ),
    );
  }

  void _confirmDelete(' . $studlyName . ' ' . $camelName . ') {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text("Confirm Delete"),
          content: Text("Are you sure you want to delete this ' . $studlyName . '?"),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text("Cancel"),
            ),
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                _delete' . $studlyName . '(' . $camelName . '.id);
              },
              child: const Text("Delete", style: TextStyle(color: Colors.red)),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("' . $studlyName . ' Management"),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => _navigateToFormScreen(null),
          ),
        ],
      ),
      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : ' . $camelName . 's.isEmpty
              ? const Center(child: Text("No ' . $studlyName . 's found"))
              : ListView.builder(
                  itemCount: ' . $camelName . 's.length,
                  itemBuilder: (context, index) {
                    final ' . $camelName . ' = ' . $camelName . 's[index];
                    return ListTile(
                      title: Text(' . $camelName . '.' . $primaryField . '.toString()),
                      subtitle: Text("ID: \${' . $camelName . '.id}"),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.edit),
                            onPressed: () => _navigateToFormScreen(' . $camelName . '),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete, color: Colors.red),
                            onPressed: () => _confirmDelete(' . $camelName . '),
                          ),
                        ],
                      ),
                      onTap: () => _navigateToFormScreen(' . $camelName . '),
                    );
                  },
                ),
    );
  }
}';

        return $content;
    }

    private function getPrimaryField($classData)
    {
        if (empty($classData['attributes'])) {
            return 'id';
        }

        foreach ($classData['attributes'] as $attr) {
            $name = strtolower($attr['name']);
            if (str_contains($name, 'nombre') || str_contains($name, 'name') || str_contains($name, 'title')) {
                return Str::camel($attr['name']);
            }
        }
        
        return Str::camel($classData['attributes'][0]['name']);
    }

    private function generateFormScreenContent($className, $classData)
    {
        $studlyName = Str::studly($className);
        $camelName = Str::camel($className);

        // Generar campos del formulario
        $formFields = "";
        $booleanFields = "";
        $booleanDeclarations = "";
        $booleanInitializations = "";
        $textControllers = "";
        $disposeControllers = "";
        $setFormData = "";

        // Construir parámetros del constructor
        $constructorParams = "";
        
        foreach ($classData['attributes'] as $attr) {
            $dartType = $this->mapToDartType($attr['type']);
            $fieldName = Str::camel($attr['name']);
            $controllerName = "_{$fieldName}Controller";
            
            if ($dartType === 'bool') {
                $booleanDeclarations .= "  bool _$fieldName = false;\n";
                $booleanInitializations .= "    if (widget.$camelName != null) {\n      _$fieldName = widget.$camelName!.$fieldName;\n    }\n";
                
                $booleanFields .= "        SwitchListTile(
          title: const Text(\"" . Str::headline($attr['name']) . "\"),
          value: _$fieldName,
          onChanged: (value) {
            setState(() {
              _$fieldName = value;
            });
          },
        ),\n";
                
                $constructorParams .= "        $fieldName: _$fieldName,\n";
            } else {
                $textControllers .= "  final TextEditingController $controllerName = TextEditingController();\n";
                $disposeControllers .= "    $controllerName.dispose();\n";
                
                $validation = ($fieldName !== 'id') ? '
          validator: (value) {
            if (value == null || value.isEmpty) {
              return "Please enter ' . Str::headline($attr['name']) . '";
            }
            return null;
          },' : '';
                
                $formFields .= "        TextFormField(
          controller: $controllerName,
          decoration: const InputDecoration(
            labelText: \"" . Str::headline($attr['name']) . "\",
          ),$validation
        ),\n";
                
                $setFormData .= "    if (widget.$camelName != null) {\n      $controllerName.text = widget.$camelName!.{$fieldName}.toString();\n    }\n";
                
                $constructorParams .= "        $fieldName: _parseValue($controllerName.text, \"$dartType\"),\n";
            }
        }

        $content = 'import \'package:flutter/material.dart\';
import \'../models/' . Str::snake($className) . '_model.dart\';
import \'../services/' . Str::snake($className) . '_service.dart\';

class ' . $studlyName . 'FormScreen extends StatefulWidget {
  final ' . $studlyName . '? ' . $camelName . ';
  final VoidCallback onSaved;

  const ' . $studlyName . 'FormScreen({
    super.key,
    this.' . $camelName . ',
    required this.onSaved,
  });

  @override
  State<' . $studlyName . 'FormScreen> createState() => _' . $studlyName . 'FormScreenState();
}

class _' . $studlyName . 'FormScreenState extends State<' . $studlyName . 'FormScreen> {
  final _formKey = GlobalKey<FormState>();
' . $textControllers . $booleanDeclarations . '
  bool isLoading = false;

  @override
  void initState() {
    super.initState();
    _setFormData();
  }

  void _setFormData() {
' . $setFormData . $booleanInitializations . '  }

  @override
  void dispose() {
' . $disposeControllers . '    super.dispose();
  }

  Future<void> _saveForm() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      isLoading = true;
    });

    try {
      final ' . $studlyName . ' ' . $camelName . ' = ' . $studlyName . '(
' . $constructorParams . '      );

      if (widget.' . $camelName . ' == null) {
        await ' . $studlyName . 'Service.create(' . $camelName . ');
      } else {
        await ' . $studlyName . 'Service.update(widget.' . $camelName . '!.id, ' . $camelName . ');
      }

      if (mounted) {
        widget.onSaved();
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          isLoading = false;
        });
        _showErrorSnackBar("Error saving ' . $studlyName . ': \$e");
      }
    }
  }

  dynamic _parseValue(String value, String type) {
    if (value.isEmpty) return null;
    
    switch (type) {
      case "int":
        return int.tryParse(value) ?? 0;
      case "double":
        return double.tryParse(value) ?? 0.0;
      case "bool":
        return value.toLowerCase() == "true";
      case "DateTime":
        return DateTime.tryParse(value) ?? DateTime.now();
      default:
        return value;
    }
  }

  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.' . $camelName . ' == null ? "Create ' . $studlyName . '" : "Edit ' . $studlyName . '"),
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: ListView(
            children: [
' . $formFields . $booleanFields . '              const SizedBox(height: 20),
              isLoading
                  ? const CircularProgressIndicator()
                  : ElevatedButton(
                      onPressed: _saveForm,
                      child: const Text("Save"),
                    ),
            ],
          ),
        ),
      ),
    );
  }
}';

        return $content;
    }

    private function generateDashboardScreen()
    {
        $menuItems = "";
        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            $studlyName = Str::studly($className);
            $menuItems .= '          ListTile(
            leading: const Icon(Icons.list),
            title: const Text("' . $studlyName . 's"),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const ' . $studlyName . 'ListScreen(),
                ),
              );
            },
          ),';
        }

        $imports = implode("\n", array_map(function($className) {
            return "import '" . Str::snake($className) . "_list_screen.dart';";
        }, array_keys($this->parsedStructure['classes'])));

        $content = 'import \'package:flutter/material.dart\';
' . $imports . '

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("ERP Dashboard"),
      ),
      body: ListView(
        children: [
' . $menuItems . '        ],
      ),
    );
  }
}';

        return $content;
    }

    /**
     * Generar widgets comunes
     */
    private function generateWidgets()
    {
        $widgets = [];
        
        // Widget de la aplicación principal
        $appContent = 'import \'package:flutter/material.dart\';
import \'./screens/dashboard_screen.dart\';

class ' . Str::studly($this->projectName) . 'App extends StatelessWidget {
  const ' . Str::studly($this->projectName) . 'App({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: "' . Str::studly($this->projectName) . ' ERP",
      theme: ThemeData(
        primarySwatch: Colors.blue,
        useMaterial3: true,
      ),
      home: const DashboardScreen(),
      debugShowCheckedModeBanner: false,
    );
  }
}';

        File::put($this->outputPath . '/lib/app.dart', $appContent);
        $widgets[] = $this->outputPath . '/lib/app.dart';

        return $widgets;
    }

    /**
     * Generar navegación
     */
    private function generateNavigation()
    {
        $routes = [];
        foreach (array_keys($this->parsedStructure['classes']) as $className) {
            $studlyName = Str::studly($className);
            $routes[] = "static const String " . Str::snake($className) . "List = \"/$studlyName\";";
        }

        $content = '// Navigation routes generated automatically
class AppRoutes {
  static const String dashboard = "/";
  ' . implode("\n  ", $routes) . '
}';

        File::ensureDirectoryExists($this->outputPath . '/lib/navigation');
        $filePath = $this->outputPath . '/lib/navigation/app_routes.dart';
        File::put($filePath, $content);
        return $filePath;
    }

    /**
     * Generar scripts de ejecución
     */
    private function generateRunScripts()
    {
        // Script para Windows
        $windowsScript = '@echo off
cd front
echo Installing Flutter dependencies...
flutter pub get
echo Starting Flutter app...
flutter run
pause';

        File::put($this->outputPath . '/../run-flutter.bat', $windowsScript);

        // Script para Linux/Mac
        $linuxScript = '#!/bin/bash
cd front
echo "Installing Flutter dependencies..."
flutter pub get
echo "Starting Flutter app..."
flutter run';

        File::put($this->outputPath . '/../run-flutter.sh', $linuxScript);
        
        // Hacer ejecutable el script de Linux
        chmod($this->outputPath . '/../run-flutter.sh', 0755);

        return [
            'windows' => $this->outputPath . '/../run-flutter.bat',
            'linux' => $this->outputPath . '/../run-flutter.sh'
        ];
    }

    /**
     * Generar test corregido
     */
    private function generateWidgetTest()
    {
        $content = 'import \'package:flutter/material.dart\';
import \'package:flutter_test/flutter_test.dart\';
import \'package:' . Str::snake($this->projectName) . '/app.dart\';

void main() {
  testWidgets(\'App starts and shows dashboard\', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(const ' . Str::studly($this->projectName) . 'App());

    // Verify that dashboard is shown
    expect(find.text(\'ERP Dashboard\'), findsOneWidget);
  });
}';

        File::ensureDirectoryExists($this->outputPath . '/test');
        File::put($this->outputPath . '/test/widget_test.dart', $content);
    }
}