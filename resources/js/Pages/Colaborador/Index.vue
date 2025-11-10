<template>
    <AppLayout title="My Collaborations">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    My Collaborations
                </h2>
                <div class="text-sm text-gray-500">
                    Diagrams where you are a collaborator
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Mostrar error si existe -->
                <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ error }}
                </div>
                
                <!-- Componente de Lista de Colaboraciones -->
                <CollaborationList 
                    :diagramas="diagramas" 
                    v-if="diagramas.length > 0"
                />
                
                <!-- Estado vacío -->
                <div v-else class="text-center py-12 bg-white rounded-lg shadow">
                    <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No collaboration diagrams yet.</p>
                    <p class="text-gray-400 text-sm mt-2">
                        You will see diagrams here when other users share them with you.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CollaborationList from '../Components/CollaborationList.vue'

// Props desde el controlador
defineProps({
    diagramas: {
        type: Array,
        required: true,
        default: () => []
    },
    error: {
        type: String,
        default: null
    }
})

// Suscribirse a canal público como en tu ejercicio
onMounted(() => {
    if (!window.Echo) {
        console.warn('Pusher no está disponible');
        return;
    }

    // Escuchar canal público 'collaborations'
    window.Echo.channel('collaborations')
        .listen('.collaborator.updated', (event) => {
            console.log('🔔 Evento recibido en colaborador:', event);
            // Recargar la página para ver cambios
            window.location.reload();
        });
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('collaborations');
    }
});
</script>