<template>
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        <!-- Header con Buscador -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h3 class="text-lg font-medium text-gray-900">
                    My Diagrams ({{ filteredDiagramas.length }})
                </h3>

                <!-- Buscador -->
                <div class="relative w-full sm:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <TextInput v-model="searchTerm" type="text" class="pl-10 w-full"
                        placeholder="Search by name or description..." />
                </div>
            </div>
        </div>

        <!-- Tabla de Diagramas -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            ID
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Name
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Collaborators
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(diagrama, index) in filteredDiagramas" :key="diagrama.id"
                        :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50 hover:bg-gray-100'"
                        class="transition-colors duration-150">
                        <!-- ID -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            #{{ diagrama.id }}
                        </td>

                        <!-- Nombre -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ diagrama.nombre }}
                            </div>
                        </td>

                        <!-- Descripción -->
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-500 max-w-xs truncate">
                                {{ diagrama.descripcion || 'No description' }}
                            </div>
                        </td>

                        <!-- Colaboradores -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-users mr-1"></i>
                                    {{ getCollaboratorsCount(diagrama) }}
                                </span>
                            </div>
                        </td>

                        <!-- Status -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="[
                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                diagrama.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                            ]">
                                <i :class="[
                                    'fas mr-1',
                                    diagrama.is_active ? 'fa-check-circle' : 'fa-pause-circle'
                                ]"></i>
                                {{ diagrama.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <!-- Acciones -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end space-x-2">
                                <!-- Ver/Editar -->
                                <button @click="viewDiagram(diagrama.id)"
                                    class="text-indigo-600 hover:text-indigo-900 transition-colors duration-150"
                                    title="View/Edit Diagram">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <!-- Exportar -->
                                <button @click="exportDiagram(diagrama)"
                                    class="text-green-600 hover:text-green-900 transition-colors duration-150"
                                    title="Export Diagram">
                                    <i class="fas fa-download"></i>
                                </button>

                                <!-- Colaboradores -->
                                <button @click="showCollaborators(diagrama)"
                                    class="text-blue-600 hover:text-blue-900 transition-colors duration-150"
                                    title="Manage Collaborators">
                                    <i class="fas fa-users"></i>
                                </button>

                                <!-- Eliminar -->
                                <button @click="confirmDelete(diagrama)"
                                    class="text-red-600 hover:text-red-900 transition-colors duration-150"
                                    title="Delete Diagram">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State -->
            <div v-if="filteredDiagramas.length === 0" class="text-center py-12">
                <i class="fas fa-diagram-project text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">
                    {{ searchTerm ? 'No diagrams found matching your search.' : 'No diagrams created yet.' }}
                </p>
                <p class="text-gray-400 text-sm mt-2">
                    {{ searchTerm ? 'Try adjusting your search terms.' : 'Create your first diagram to get started!' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Modal de Colaboradores -->
    <CollaboratorsModal :show="showCollaboratorsModal" :diagram="selectedDiagram"
        @close="showCollaboratorsModal = false" @updated="handleCollaboratorsUpdated" />

    <!-- Modal de Confirmación para Eliminar -->
    <ConfirmationModal :show="showDeleteModal" @close="showDeleteModal = false">
        <template #title>
            Delete Diagram
        </template>

        <template #content>
            <div class="flex items-center mb-4">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
            </div>
            <p class="text-center text-gray-700">
                Are you sure you want to delete the diagram
                <span class="font-semibold">"{{ diagramToDelete?.nombre }}"</span>?
            </p>
            <p class="text-center text-sm text-gray-500 mt-2">
                This action cannot be undone.
            </p>
        </template>

        <template #footer>
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="showDeleteModal = false">
                    Cancel
                </SecondaryButton>
                <DangerButton @click="deleteDiagram" :disabled="deleteLoading">
                    <span v-if="deleteLoading">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Deleting...
                    </span>
                    <span v-else>
                        <i class="fas fa-trash mr-2"></i>
                        Delete Diagram
                    </span>
                </DangerButton>
            </div>
        </template>
    </ConfirmationModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import TextInput from '@/Components/TextInput.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import ConfirmationModal from '@/Components/ConfirmationModal.vue'
import CollaboratorsModal from './CollaboratorsModal.vue'

// Props
const props = defineProps({
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

// Estado reactivo
const searchTerm = ref('')
const showDeleteModal = ref(false)
const showCollaboratorsModal = ref(false)
const deleteLoading = ref(false)
const diagramToDelete = ref(null)
const selectedDiagram = ref(null)

// Filtrado que no distingue mayúsculas/minúsculas
const filteredDiagramas = computed(() => {
    if (!searchTerm.value.trim()) {
        return props.diagramas
    }

    const term = searchTerm.value.toLowerCase()
    return props.diagramas.filter(diagrama =>
        diagrama.nombre.toLowerCase().includes(term) ||
        (diagrama.descripcion && diagrama.descripcion.toLowerCase().includes(term))
    )
})

// Métodos
const getCollaboratorsCount = (diagram) => {
    return diagram.colaboradores_actuales ? diagram.colaboradores_actuales.length : 0
}

const getCurrentCollaborators = (diagram) => {
    if (!diagram || !diagram.colaboradores_actuales) return []
    return diagram.colaboradores_actuales
}

const viewDiagram = (id) => {
    window.open(route('diagrams.show', id), '_blank');
}

const exportDiagram = (diagrama) => {
    console.log('Exporting diagram:', diagrama)
    // FIX: Usa window.open para bypass Inertia y forzar descarga
    const url = route('diagrams.export', diagrama.id);
    window.open(url, '_blank');
}

const showCollaborators = (diagrama) => {
    selectedDiagram.value = diagrama
    showCollaboratorsModal.value = true
}

const handleCollaboratorsUpdated = () => {
    // Recargar los datos para reflejar los cambios en colaboradores
    router.reload({ only: ['diagramas'] })
}

const confirmDelete = (diagrama) => {
    diagramToDelete.value = diagrama
    showDeleteModal.value = true
}

const deleteDiagram = async () => {
    if (!diagramToDelete.value) return

    deleteLoading.value = true

    try {
        await router.delete(route('diagrams.destroy', diagramToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false
                diagramToDelete.value = null
            },
            onError: (errors) => {
                console.error('Error deleting diagram:', errors)
            }
        })
    } catch (error) {
        console.error('Error deleting diagram:', error)
    } finally {
        deleteLoading.value = false
    }
}
</script>