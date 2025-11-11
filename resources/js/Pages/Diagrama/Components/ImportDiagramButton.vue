<template>
    <div class="relative">
        <!-- Botón para abrir el diálogo de importación -->
        <button
            @click="openModal = true"
            class="inline-flex items-center justify-center px-4 py-2 bg-white border border-indigo-300 rounded-lg font-semibold text-sm text-indigo-700 shadow-sm
                   transition-transform duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg hover:bg-indigo-50 cursor-pointer"
            :disabled="loading"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Importar Imagen UML
        </button>

        <!-- Modal de Importación -->
        <div v-if="openModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Importar Diagrama desde Imagen</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Selecciona una imagen de diagrama UML
                    </label>
                    <input
                        type="file"
                        ref="fileInput"
                        @change="handleFileSelect"
                        accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        Formatos soportados: JPG, JPEG, PNG, GIF, WEBP (Max: 2MB)
                    </p>
                </div>

                <!-- Información del archivo seleccionado -->
                <div v-if="selectedFile" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-sm text-blue-700">
                        <strong>Archivo seleccionado:</strong> {{ selectedFile.name }}
                    </p>
                </div>

                <!-- Estado de carga -->
                <div v-if="loading" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-sm text-blue-600 font-medium">🔄 Analizando diagrama UML...</p>
                    <p class="text-xs text-blue-500 mt-1">Esto puede tomar unos segundos</p>
                </div>

                <!-- Resultado -->
                <div v-if="result.message" class="mb-4 p-4 rounded" :class="result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                    <p class="text-sm whitespace-pre-line" :class="result.success ? 'text-green-700' : 'text-red-700'">
                        {{ result.message }}
                    </p>
                    
                    <!-- Botón para abrir editor si fue exitoso -->
                    <div v-if="result.success && result.diagrama_id" class="mt-3">
                        <button
                            @click="openEditor"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            Abrir Editor UML
                        </button>
                        <p class="text-xs text-green-600 mt-2">Serás redirigido al editor del diagrama</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button
                        @click="closeModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                        :disabled="loading"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="importDiagram"
                        :disabled="!selectedFile || loading"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ loading ? 'Procesando...' : 'Importar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3'

export default {
    name: 'ImportDiagramButton',
    data() {
        return {
            openModal: false,
            loading: false,
            selectedFile: null,
            result: {
                success: false,
                message: '',
                diagrama_id: null,
                redirect_url: null
            }
        }
    },
    methods: {
        handleFileSelect(event) {
            const file = event.target.files[0]
            if (file) {
                // Validar tipo de archivo
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
                if (!validTypes.includes(file.type)) {
                    this.result = {
                        success: false,
                        message: '❌ Tipo de archivo no válido. Use JPG, PNG, GIF o WEBP.'
                    }
                    this.selectedFile = null
                    return
                }

                // Validar tamaño (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    this.result = {
                        success: false,
                        message: '❌ El archivo es demasiado grande. Máximo 2MB.'
                    }
                    this.selectedFile = null
                    return
                }

                this.selectedFile = file
                this.result.message = ''
            }
        },

        async importDiagram() {
            if (!this.selectedFile) {
                this.result = {
                    success: false,
                    message: '❌ Por favor selecciona un archivo.'
                }
                return
            }

            this.loading = true
            this.result.message = ''

            const formData = new FormData()
            formData.append('file', this.selectedFile)

            try {
                const response = await fetch('/diagramas/import-from-image', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })

                const data = await response.json()

                this.result = {
                    success: data.success,
                    message: data.message,
                    diagrama_id: data.diagrama_id,
                    redirect_url: data.redirect_url
                }

                if (data.success) {
                    // Resetear el formulario
                    this.selectedFile = null
                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = ''
                    }
                }

            } catch (error) {
                this.result = {
                    success: false,
                    message: '❌ Error de conexión. Intenta nuevamente.'
                }
            } finally {
                this.loading = false
            }
        },

        openEditor() {
            if (this.result.redirect_url) {
                router.visit(this.result.redirect_url)
                this.closeModal()
            }
        },

        closeModal() {
            this.openModal = false
            this.selectedFile = null
            this.result.message = ''
            this.loading = false
            
            // Resetear input de archivo
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = ''
            }
        }
    }
}
</script>