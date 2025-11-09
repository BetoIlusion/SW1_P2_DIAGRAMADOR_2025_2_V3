<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Dashboard
                </h2>

                <PrimaryButton @click="showCreateModal = true">
                    <i class="fas fa-plus mr-2"></i>
                    Create Diagram
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Componente de Lista de Diagramas -->
                <DiagramList :diagramas="diagramas" :usuarios="usuarios" />
            </div>
        </div>

        <!-- Modal Component -->
        <CreateDiagramModal :show="showCreateModal" @close="showCreateModal = false" />
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import CreateDiagramModal from './Components/CreateDiagramModal.vue'
import DiagramList from './Components/DiagramList.vue'

// Props desde el controlador
defineProps({
    diagramas: {
        type: Array,
        required: true,
        default: () => []
    },
    usuarios: {
        type: Array,
        required: true,
        default: () => []
    }
})

const showCreateModal = ref(false)
</script>