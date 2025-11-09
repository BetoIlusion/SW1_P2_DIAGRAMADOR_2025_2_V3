<template>
    <Modal :show="show" @close="closeModal" max-width="md">
        <div class="p-0">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">
                    Manage Collaborators
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    Diagram: "{{ selectedDiagram?.nombre }}"
                </p>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-6 max-h-96 overflow-y-auto">
                <!-- Current Collaborators -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-users mr-2 text-blue-500"></i>
                        Current Collaborators ({{ currentCollaborators.length }})
                    </h4>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full">
                            <tbody class="divide-y divide-gray-200">
                                <tr 
                                    v-for="user in currentCollaborators" 
                                    :key="user.id"
                                    class="hover:bg-gray-50 transition-colors"
                                >
                                    <td class="p-3 text-sm text-gray-700">
                                        <div class="flex items-center">
                                            <i class="fas fa-user-circle text-gray-400 mr-2"></i>
                                            {{ user.name }}
                                            <span class="text-xs text-gray-400 ml-2">({{ user.email }})</span>
                                        </div>
                                    </td>
                                    <td class="p-3 w-16 text-right">
                                        <button 
                                            @click="removeCollaborator(user.id)"
                                            :disabled="removingUserId === user.id"
                                            class="text-red-500 hover:text-red-700 transition-colors disabled:opacity-50"
                                            title="Remove collaborator"
                                        >
                                            <i 
                                                v-if="removingUserId === user.id" 
                                                class="fas fa-spinner fa-spin"
                                            ></i>
                                            <i v-else class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="currentCollaborators.length === 0">
                                    <td colspan="2" class="p-4 text-center text-sm text-gray-500">
                                        No collaborators added yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Available Users -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-user-plus mr-2 text-green-500"></i>
                        Available Users ({{ availableUsers.length }})
                    </h4>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="w-full">
                            <tbody class="divide-y divide-gray-200">
                                <tr 
                                    v-for="user in availableUsers" 
                                    :key="user.id"
                                    class="hover:bg-gray-50 transition-colors"
                                >
                                    <td class="p-3 text-sm text-gray-700">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-gray-400 mr-2"></i>
                                            {{ user.name }}
                                            <span class="text-xs text-gray-400 ml-2">({{ user.email }})</span>
                                        </div>
                                    </td>
                                    <td class="p-3 w-16 text-right">
                                        <button 
                                            @click="addCollaborator(user.id)"
                                            :disabled="addingUserId === user.id"
                                            class="text-green-500 hover:text-green-700 transition-colors disabled:opacity-50"
                                            title="Add as collaborator"
                                        >
                                            <i 
                                                v-if="addingUserId === user.id" 
                                                class="fas fa-spinner fa-spin"
                                            ></i>
                                            <i v-else class="fas fa-plus"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="availableUsers.length === 0">
                                    <td colspan="2" class="p-4 text-center text-sm text-gray-500">
                                        All users are already collaborators.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="px-6 pb-4">
                <div 
                    v-if="successMessage" 
                    class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded text-sm"
                >
                    <i class="fas fa-check-circle mr-2"></i>
                    {{ successMessage }}
                </div>
                <div 
                    v-else-if="errorMessage" 
                    class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm"
                >
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    {{ errorMessage }}
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-3 flex justify-end rounded-b-lg">
                <SecondaryButton @click="closeModal">
                    Close
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'

// Props
const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    diagram: {
        type: Object,
        default: null
    }
})

// Emits
const emit = defineEmits(['close', 'updated'])

// Estado reactivo
const selectedDiagram = ref(null)
const addingUserId = ref(null)
const removingUserId = ref(null)
const successMessage = ref('')
const errorMessage = ref('')

// Computed: Colaboradores actuales del diagrama seleccionado
const currentCollaborators = computed(() => {
    if (!selectedDiagram.value || !selectedDiagram.value.colaboradores_actuales) {
        return []
    }
    return selectedDiagram.value.colaboradores_actuales
})

// Computed: Usuarios disponibles para este diagrama específico
const availableUsers = computed(() => {
    if (!selectedDiagram.value || !selectedDiagram.value.usuarios_no_colaboradores) {
        return []
    }
    return selectedDiagram.value.usuarios_no_colaboradores
})

// Watchers
watch(() => props.show, (newVal) => {
    if (newVal) {
        selectedDiagram.value = props.diagram
        clearMessages()
        console.log('🔍 Diagrama seleccionado:', selectedDiagram.value)
        console.log('👥 Colaboradores actuales:', currentCollaborators.value)
        console.log('📋 Usuarios no colaboradores:', availableUsers.value)
    }
})

// Métodos
const clearMessages = () => {
    successMessage.value = ''
    errorMessage.value = ''
}

const closeModal = () => {
    clearMessages()
    selectedDiagram.value = null
    emit('close')
}

const addCollaborator = async (userId) => {
    if (!selectedDiagram.value) return

    addingUserId.value = userId
    clearMessages()

    try {
        await router.post(route('diagrams.collaborators.add'), {
            diagrama_id: selectedDiagram.value.id,
            user_id: userId
        }, {
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Collaborator added successfully!'
                // Emitir evento para que el padre recargue los datos
                emit('updated')
            },
            onError: (errors) => {
                errorMessage.value = errors.message || 'Error adding collaborator'
            }
        })
    } catch (error) {
        errorMessage.value = error.message || 'Error adding collaborator'
    } finally {
        addingUserId.value = null
    }
}

const removeCollaborator = async (userId) => {
    if (!selectedDiagram.value) return

    removingUserId.value = userId
    clearMessages()

    try {
        await router.post(route('diagrams.collaborators.remove'), {
            diagrama_id: selectedDiagram.value.id,
            user_id: userId
        }, {
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Collaborator removed successfully!'
                // Emitir evento para que el padre recargue los datos
                emit('updated')
            },
            onError: (errors) => {
                errorMessage.value = errors.message || 'Error removing collaborator'
            }
        })
    } catch (error) {
        errorMessage.value = error.message || 'Error removing collaborator'
    } finally {
        removingUserId.value = null
    }
}
</script>

<style scoped>
.max-h-96 {
    max-height: 24rem;
}

.transition-colors {
    transition-property: color, background-color, border-color;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}
</style>