<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use ZipArchive;
use Illuminate\Support\Facades\Log;


class ProjectZipperService
{
    public function createZip($projectPath, $zipFileName = null)
    {
        try {
            if (!$zipFileName) {
                $zipFileName = basename($projectPath) . '.zip';
            }
            
            // Asegurar que la ruta sea absoluta
            $zipPath = storage_path('app/generated-projects/' . $zipFileName);
            
            Log::info("Creando ZIP en: {$zipPath}");
            Log::info("Comprimiendo carpeta: {$projectPath}");
            
            $zip = new ZipArchive();
            $status = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            if ($status === TRUE) {
                $this->addFolderToZip($projectPath, $zip, '');
                $zip->close();
                
                Log::info("ZIP creado exitosamente: {$zipPath}");
                return $zipPath;
            } else {
                Log::error("Error al abrir ZIP. Código: {$status}");
                throw new \Exception("No se pudo crear el archivo ZIP. Código: " . $status);
            }
            
        } catch (\Exception $e) {
            Log::error("Error en createZip: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function addFolderToZip($folder, $zip, $parentFolder = '')
    {
        Log::info("Agregando carpeta al ZIP: {$folder}");
        
        // Agregar archivos
        $files = File::files($folder);
        foreach ($files as $file) {
            $relativePath = $parentFolder . basename($file);
            Log::info("Agregando archivo: {$relativePath}");
            $zip->addFile($file, $relativePath);
        }
        
        // Agregar subcarpetas recursivamente
        $directories = File::directories($folder);
        foreach ($directories as $directory) {
            $relativePath = $parentFolder . basename($directory) . '/';
            Log::info("Agregando carpeta: {$relativePath}");
            $zip->addEmptyDir($relativePath);
            $this->addFolderToZip($directory, $zip, $relativePath);
        }
    }
}