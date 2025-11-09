<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Create New Diagram
            </h2>

            <form @submit.prevent="submit">
                <div class="space-y-4">
                    <!-- Diagram Name -->
                    <div>
                        <InputLabel for="name" value="Diagram Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            placeholder="Enter diagram name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Description (Optional)" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            placeholder="Brief description of your diagram"
                        />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>
                    
                    <PrimaryButton 
                        :class="{ 'opacity-25': form.processing }" 
                        :disabled="form.processing"
                        type="submit"
                    >
                        Create Diagram
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

// Props
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    }
})

// Emits
const emit = defineEmits(['close'])

// Form
const form = useForm({
    name: '',
    // type: '', // Removed type
    description: '',
})

// Methods
const closeModal = () => {
    form.reset()
    form.clearErrors()
    emit('close')
}

const submit = () => {
    form.post(route('diagrams.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
            // Optional: Show success message or redirect
            router.visit(route('diagrams.index'))
        },
        onError: () => {
            // Errors are automatically handled by form.errors
        }
    })
}
</script>