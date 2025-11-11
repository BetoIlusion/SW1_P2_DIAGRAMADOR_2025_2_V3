<template>
    <div>
        <label
            class="inline-flex items-center justify-center px-4 py-2 bg-white border border-indigo-300 rounded-lg font-semibold text-sm text-indigo-700 shadow-sm
                   transition-transform duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg hover:bg-indigo-50 cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Importar Imagen UML
            <input type="file" @change="handleFileChange" class="hidden" accept="image/*">
        </label>
        
        <p v-if="form.file" class="text-sm text-gray-500 mt-1">{{ form.file.name }}</p>
        
        <p v-if="form.errors.file" class="text-sm text-red-600 mt-1">{{ form.errors.file }}</p>
        
        <div v-if="isLoading" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded">
            <p class="text-sm text-blue-600 font-medium">🔄 Analizando diagrama UML...</p>
            <p class="text-xs text-blue-500 mt-1">Esto puede tomar unos segundos</p>
        </div>
        
        <div v-if="resultado" class="mt-3 p-4 bg-white border border-green-200 rounded-lg shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Resultado del análisis:</h3>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ resultado }}</p>
            
            <div v-if="diagramaCreado" class="mt-3 p-3 bg-green-50 border border-green-200 rounded">
                <button @click="abrirEditor"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    Abrir Editor UML
                </button>
                <p class="text-xs text-green-600 mt-2">Serás redirigido al editor del diagrama</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const form = useForm({
    file: null,
});

const isLoading = ref(false);
const resultado = ref('');
const diagramaCreado = ref(null);

const handleFileChange = (e) => {
    form.file = e.target.files[0];
    if (form.file) {
        uploadImage();
    }
};

const uploadImage = () => {
    isLoading.value = true;
    resultado.value = '';
    diagramaCreado.value = null;

    form.post(route('diagrams.import-image'), {
        preserveScroll: true,
        onSuccess: (response) => {
            // resultado.value = response.props.resultado || 'Diagrama creado exitosamente.';
            // diagramaCreado.value = response.props.diagramaId;
        },
        onError: (errors) => {
            resultado.value = 'Error: ' + (errors.file || 'Desconocido');
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

const abrirEditor = () => {
    if (diagramaCreado.value) {
        router.visit(route('diagrams.show', diagramaCreado.value));
    } else {
        resultado.value = '❌ No hay diagrama disponible para abrir.';
    }
};
</script>