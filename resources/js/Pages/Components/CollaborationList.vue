<template>
    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">
                        Collaboration Diagrams ({{ filteredDiagramas.length }})
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Diagrams shared with you for collaboration
                    </p>
                </div>
                
                <!-- Buscador -->
                <div class="relative w-full sm:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <TextInput
                        v-model="searchTerm"
                        type="text"
                        class="pl-10 w-full"
                        placeholder="Search by name, description or creator..."
                    />
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
                            Diagram Name
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created By
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr 
                        v-for="(diagrama, index) in filteredDiagramas" 
                        :key="diagrama.id"
                        :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50 hover:bg-gray-100'"
                        class="transition-colors duration-150"
                    >
                        <!-- ID -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            #{{ diagrama.id }}
                        </td>

                        <!-- Nombre del Diagrama -->
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

                        <!-- Creador -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <i class="fas fa-user-circle text-gray-400 mr-2"></i>
                                <span class="text-sm text-gray-700">
                                    {{ getCreatorName(diagrama) }}
                                </span>
                            </div>
                        </td>

                        <!-- Acciones -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end space-x-2">
                                <!-- Ver Diagrama -->
                                <button
                                    @click="viewDiagram(diagrama.id)"
                                    class="text-indigo-600 hover:text-indigo-900 transition-colors duration-150"
                                    title="View Diagram"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>

                                <!-- Exportar -->
                                <button
                                    @click="exportDiagram(diagrama)"
                                    class="text-green-600 hover:text-green-900 transition-colors duration-150"
                                    title="Export Diagram"
                                >
                                    <i class="fas fa-download"></i>
                                </button>

                                <!-- Dejar de Colaborar -->
                                <button
                                    @click="confirmLeaveCollaboration(diagrama)"
                                    class="text-red-600 hover:text-red-900 transition-colors duration-150"
                                    title="Leave Collaboration"
                                >
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty State -->
            <div 
                v-if="filteredDiagramas.length === 0" 
                class="text-center py-12"
            >
                <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">
                    {{ searchTerm ? 'No collaborations found matching your search.' : 'No collaboration diagrams yet.' }}
                </p>
                <p class="text-gray-400 text-sm mt-2">
                    {{ searchTerm ? 'Try adjusting your search terms.' : 'You will see diagrams here when other users share them with you.' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación para Dejar Colaboración -->
    <ConfirmationModal :show="showLeaveModal" @close="showLeaveModal = false">
        <template #title>
            Leave Collaboration
        </template>

        <template #content>
            <div class="flex items-center mb-4">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100">
                    <i class="fas fa-sign-out-alt text-orange-600 text-xl"></i>
                </div>
            </div>
            <p class="text-center text-gray-700">
                Are you sure you want to leave the collaboration for diagram 
                <span class="font-semibold">"{{ diagramToLeave?.nombre }}"</span>?
            </p>
            <p class="text-center text-sm text-gray-500 mt-2">
                You will no longer have access to this diagram. This action can be reversed if the owner adds you again.
            </p>
        </template>

        <template #footer>
            <div class="flex justify-end space-x-3">
                <SecondaryButton @click="showLeaveModal = false">
                    Cancel
                </SecondaryButton>
                <DangerButton @click="leaveCollaboration" :disabled="leaveLoading">
                    <span v-if="leaveLoading">
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Leaving...
                    </span>
                    <span v-else>
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Leave Collaboration
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

// Props
const props = defineProps({
    diagramas: {
        type: Array,
        required: true,
        default: () => []
    }
})

// Estado reactivo
const searchTerm = ref('')
const showLeaveModal = ref(false)
const leaveLoading = ref(false)
const diagramToLeave = ref(null)

// Filtrado
const filteredDiagramas = computed(() => {
    if (!searchTerm.value.trim()) {
        return props.diagramas
    }

    const term = searchTerm.value.toLowerCase()
    return props.diagramas.filter(diagrama => 
        diagrama.nombre.toLowerCase().includes(term) ||
        (diagrama.descripcion && diagrama.descripcion.toLowerCase().includes(term)) ||
        (getCreatorName(diagrama).toLowerCase().includes(term))
    )
})

// Métodos
const getCreatorName = (diagrama) => {
    if (diagrama.creator_name) return diagrama.creator_name
    if (diagrama.creador) return diagrama.creador.name
    if (diagrama.usuario_diagramas) {
        const creador = diagrama.usuario_diagramas.find(ud => ud.tipo_usuario === 'creador')
        if (creador && creador.user) return creador.user.name
    }
    return 'Unknown Creator'
}

const viewDiagram = (id) => {
    router.visit(route('diagrams.show', id))
}

const exportDiagram = (diagrama) => {
    // Abrir en nueva pestaña para descargar el archivo
    window.open(route('diagrams.export', diagrama.id), '_blank');
}

const confirmLeaveCollaboration = (diagrama) => {
    diagramToLeave.value = diagrama
    showLeaveModal.value = true
}

const leaveCollaboration = async () => {
    if (!diagramToLeave.value) return

    leaveLoading.value = true

    try {
        await router.post(route('collaborations.leave', diagramToLeave.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showLeaveModal.value = false  // ← CERRAR MODAL AQUÍ
                diagramToLeave.value = null
                router.reload({ only: ['diagramas'] })  // ← Actualiza lista sin recargar página
            },
            onError: (errors) => {
                console.error('Error leaving collaboration:', errors)
                alert('Error leaving collaboration. Please try again.')
            }
        })
    } catch (error) {
        console.error('Error leaving collaboration:', error)
        alert('Error leaving collaboration. Please try again.')
    } finally {
        leaveLoading.value = false
    }
}
</script>

<style scoped>
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.transition-colors {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>