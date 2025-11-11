<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SpringBootBackendGeneratorService
{
    private $parsedStructure;
    private $projectName;
    private $outputPath;

    public function __construct($parsedStructure, $projectName, $outputPath)
    {
        $this->parsedStructure = $parsedStructure;
        $this->projectName = $projectName;
        $this->outputPath = $outputPath . '/spring-boot';
    }

    public function generateBackend()
    {
        try {
            // Generar archivos de configuración
            $this->generateConfigurationFiles();

            // Generar entidades
            $entities = $this->generateEntities();

            // Generar repositorios
            $repositories = $this->generateRepositories();

            // Generar servicios
            $services = $this->generateServices();

            // Generar controladores
            $controllers = $this->generateControllers();

            // Generar DTOs (si se necesitan)
            $dtos = $this->generateDTOs();

            return [
                'success' => true,
                'project_path' => $this->outputPath,
                'entities' => $entities,
                'repositories' => $repositories,
                'services' => $services,
                'controllers' => $controllers,
                'dtos' => $dtos,
                'message' => 'Backend Spring Boot generado exitosamente'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function generateConfigurationFiles()
    {
        $this->generatePomXml();
        $this->generateApplicationProperties();
        $this->generateMainApplicationClass();
    }

    private function generatePomXml()
    {
        $content = '<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0 
         http://maven.apache.org/xsd/maven-4.0.0.xsd">
    <modelVersion>4.0.0</modelVersion>

    <groupId>com.example</groupId>
    <artifactId>' . $this->projectName . '</artifactId>
    <version>1.0.0</version>
    <packaging>jar</packaging>

    <name>' . $this->projectName . '</name>
    <description>ERP Spring Boot application generated from UML diagram</description>

    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.2.0</version>
        <relativePath/>
    </parent>

    <properties>
        <java.version>17</java.version>
    </properties>

    <dependencies>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>
        <dependency>
            <groupId>com.h2database</groupId>
            <artifactId>h2</artifactId>
            <scope>runtime</scope>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>
    </dependencies>

    <build>
        <plugins>
            <plugin>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-maven-plugin</artifactId>
            </plugin>
        </plugins>
    </build>
</project>';

        File::ensureDirectoryExists($this->outputPath);
        File::put($this->outputPath . '/pom.xml', $content);
    }

    private function generateApplicationProperties()
    {
        $content = 'spring.datasource.url=jdbc:h2:mem:testdb
spring.datasource.driverClassName=org.h2.Driver
spring.datasource.username=sa
spring.datasource.password=
spring.h2.console.enabled=true
spring.jpa.database-platform=org.hibernate.dialect.H2Dialect
spring.jpa.hibernate.ddl-auto=create-drop
spring.jpa.show-sql=true';

        File::ensureDirectoryExists($this->outputPath . '/src/main/resources');
        File::put($this->outputPath . '/src/main/resources/application.properties', $content);
    }

    private function generateMainApplicationClass()
    {
        $content = 'package com.example.' . $this->projectName . ';

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class ' . Str::studly($this->projectName) . 'Application {

    public static void main(String[] args) {
        SpringApplication.run(' . Str::studly($this->projectName) . 'Application.class, args);
    }
}';

        File::ensureDirectoryExists($this->outputPath . '/src/main/java/com/example/' . $this->projectName);
        File::put($this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/' . Str::studly($this->projectName) . 'Application.java', $content);
    }

    private function generateEntities()
    {
        $entities = [];

        File::ensureDirectoryExists($this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/entity');

        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            $entityContent = $this->generateEntityContent($className, $classData);
            $filePath = $this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/entity/' . Str::studly($className) . '.java';
            File::put($filePath, $entityContent);
            $entities[] = $filePath;
        }

        return $entities;
    }

    private function generateEntityContent($className, $classData)
    {
        $studlyName = Str::studly($className);

        // Identificar y manejar correctamente el campo ID
        $idFieldProcessed = false;
        $fields = "";
        $imports = ["import jakarta.persistence.*;"];
        $gettersSetters = "";

        foreach ($classData['attributes'] as $attr) {
            $javaType = $this->mapToJavaType($attr['type']);
            $fieldName = Str::camel($attr['name']);
            $capitalizedName = Str::studly($attr['name']);

            // DETECCIÓN INTELIGENTE DE ID: Si es el campo 'id' o si el nombre sugiere que es ID
            $isIdField = false;
            if (strtolower($fieldName) === 'id' || 
                str_contains(strtolower($fieldName), 'id') || 
                str_contains(strtolower($attr['name']), 'id')) {
                
                // Solo marcar como ID si no hemos procesado ya uno
                if (!$idFieldProcessed) {
                    $isIdField = true;
                    $idFieldProcessed = true;
                    // Forzar tipo Long para IDs
                    $javaType = 'Long';
                }
            }

            // Generar anotaciones JPA
            $annotations = "";
            if ($isIdField) {
                $annotations = "    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n";
                // Agregar import si no existe
                if (!in_array('import jakarta.persistence.*;', $imports)) {
                    $imports[] = 'import jakarta.persistence.*;';
                }
            }

            // Campo
            $fields .= $annotations . "    private {$javaType} {$fieldName};\n\n";

            // Getter y Setter
            $gettersSetters .= "    public {$javaType} get{$capitalizedName}() {\n        return {$fieldName};\n    }\n\n";
            $gettersSetters .= "    public void set{$capitalizedName}({$javaType} {$fieldName}) {\n        this.{$fieldName} = {$fieldName};\n    }\n\n";
        }

        // GARANTIZAR QUE HAYA UN ID: Si no se detectó ningún campo como ID, agregar uno automáticamente
        if (!$idFieldProcessed) {
            // Insertar el ID al principio
            $idField = "    @Id\n    @GeneratedValue(strategy = GenerationType.IDENTITY)\n    private Long id;\n\n";
            $fields = $idField . $fields;
            
            // Agregar getters y setters para el ID
            $idGettersSetters = "    public Long getId() {\n        return id;\n    }\n\n    public void setId(Long id) {\n        this.id = id;\n    }\n\n";
            $gettersSetters = $idGettersSetters . $gettersSetters;
        }

        $content = 'package com.example.' . $this->projectName . '.entity;

' . implode("\n", array_unique($imports)) . '

@Entity
@Table(name = "' . Str::snake(Str::plural($className)) . '")
public class ' . $studlyName . ' {

' . $fields . '
    // Getters and Setters
' . $gettersSetters . '}';

        return $content;
    }

    private function generateRepositories()
    {
        $repositories = [];

        File::ensureDirectoryExists($this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/repository');

        foreach ($this->parsedStructure['classes'] as $className => $classData) {
            $studlyName = Str::studly($className);

            $content = 'package com.example.' . $this->projectName . '.repository;

import com.example.' . $this->projectName . '.entity.' . $studlyName . ';
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface ' . $studlyName . 'Repository extends JpaRepository<' . $studlyName . ', Long> {
}';

            $filePath = $this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/repository/' . $studlyName . 'Repository.java';
            File::put($filePath, $content);
            $repositories[] = $filePath;
        }

        return $repositories;
    }

    private function generateServices()
{
    $services = [];
    
    File::ensureDirectoryExists($this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/service');
    
    foreach ($this->parsedStructure['classes'] as $className => $classData) {
        $studlyName = Str::studly($className);
        $camelName = Str::camel($className);

        $content = 'package com.example.' . $this->projectName . '.service;

import com.example.' . $this->projectName . '.entity.' . $studlyName . ';
import com.example.' . $this->projectName . '.repository.' . $studlyName . 'Repository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.Optional;

@Service
public class ' . $studlyName . 'Service {

    @Autowired
    private ' . $studlyName . 'Repository ' . $camelName . 'Repository;

    public List<' . $studlyName . '> findAll() {
        return ' . $camelName . 'Repository.findAll();
    }

    public Optional<' . $studlyName . '> findById(Long id) {
        return ' . $camelName . 'Repository.findById(id);
    }

    public ' . $studlyName . ' save(' . $studlyName . ' ' . $camelName . ') {
        return ' . $camelName . 'Repository.save(' . $camelName . ');
    }

    public ' . $studlyName . ' update(Long id, ' . $studlyName . ' ' . $camelName . ') {
        Optional<' . $studlyName . '> existing' . $studlyName . ' = ' . $camelName . 'Repository.findById(id);
        if (existing' . $studlyName . '.isPresent()) {
            ' . $camelName . '.setId(id);
            return ' . $camelName . 'Repository.save(' . $camelName . ');
        } else {
            throw new RuntimeException("' . $studlyName . ' not found with id: " + id);
        }
    }

    public void deleteById(Long id) {
        if (' . $camelName . 'Repository.existsById(id)) {
            ' . $camelName . 'Repository.deleteById(id);
        } else {
            throw new RuntimeException("' . $studlyName . ' not found with id: " + id);
        }
    }

    public boolean existsById(Long id) {
        return ' . $camelName . 'Repository.existsById(id);
    }
}';

        $filePath = $this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/service/' . $studlyName . 'Service.java';
        File::put($filePath, $content);
        $services[] = $filePath;
    }

    return $services;
}

    private function generateControllers()
{
    $controllers = [];

    File::ensureDirectoryExists($this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/controller');

    foreach ($this->parsedStructure['classes'] as $className => $classData) {
        $studlyName = Str::studly($className);
        $camelName = Str::camel($className);

        $content = 'package com.example.' . $this->projectName . '.controller;

import com.example.' . $this->projectName . '.entity.' . $studlyName . ';
import com.example.' . $this->projectName . '.service.' . $studlyName . 'Service;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Optional;

@RestController
@RequestMapping("/api/' . Str::snake(Str::plural($className)) . '")
@CrossOrigin(origins = "*")
public class ' . $studlyName . 'Controller {

    @Autowired
    private ' . $studlyName . 'Service ' . $camelName . 'Service;

    @GetMapping
    public List<' . $studlyName . '> findAll() {
        return ' . $camelName . 'Service.findAll();
    }

    @GetMapping("/{id}")
    public ResponseEntity<' . $studlyName . '> findById(@PathVariable Long id) {
        Optional<' . $studlyName . '> ' . $camelName . ' = ' . $camelName . 'Service.findById(id);
        return ' . $camelName . '.map(ResponseEntity::ok).orElse(ResponseEntity.notFound().build());
    }

    @PostMapping
    public ' . $studlyName . ' create(@RequestBody ' . $studlyName . ' ' . $camelName . ') {
        return ' . $camelName . 'Service.save(' . $camelName . ');
    }

    @PutMapping("/{id}")
    public ResponseEntity<?> update(@PathVariable Long id, @RequestBody ' . $studlyName . ' ' . $camelName . ') {
        try {
            ' . $studlyName . ' updated' . $studlyName . ' = ' . $camelName . 'Service.update(id, ' . $camelName . ');
            return ResponseEntity.ok(updated' . $studlyName . ');
        } catch (RuntimeException e) {
            if (e.getMessage().contains("not found")) {
                return ResponseEntity.status(HttpStatus.NOT_FOUND)
                    .body("Error: ' . $studlyName . ' with id " + id + " not found");
            }
            return ResponseEntity.status(HttpStatus.BAD_REQUEST)
                .body("Error updating ' . $studlyName . ': " + e.getMessage());
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body("Unexpected error updating ' . $studlyName . ': " + e.getMessage());
        }
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<?> deleteById(@PathVariable Long id) {
        try {
            ' . $camelName . 'Service.deleteById(id);
            return ResponseEntity.ok().build();
        } catch (RuntimeException e) {
            if (e.getMessage().contains("not found")) {
                return ResponseEntity.status(HttpStatus.NOT_FOUND)
                    .body("Error: ' . $studlyName . ' with id " + id + " not found");
            }
            return ResponseEntity.status(HttpStatus.BAD_REQUEST)
                .body("Error deleting ' . $studlyName . ': " + e.getMessage());
        } catch (Exception e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body("Unexpected error deleting ' . $studlyName . ': " + e.getMessage());
        }
    }
}';

        $filePath = $this->outputPath . '/src/main/java/com/example/' . $this->projectName . '/controller/' . $studlyName . 'Controller.java';
        File::put($filePath, $content);
        $controllers[] = $filePath;
    }

    return $controllers;
}

    private function generateDTOs()
    {
        // Por simplicidad, no generamos DTOs, pero se puede extender
        return [];
    }

    private function mapToJavaType($type)
    {
        $typeMap = [
            'String' => 'String',
            'Integer' => 'Integer',
            'Long' => 'Long',
            'Double' => 'Double',
            'Float' => 'Float',
            'Boolean' => 'Boolean',
            'LocalDate' => 'java.time.LocalDate',
            'LocalDateTime' => 'java.time.LocalDateTime',
        ];

        return $typeMap[$type] ?? 'String';
    }
}