<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const buttonColor = ref('bg-gray-500'); // Initial color

onMounted(() => {
    window.Echo.channel('click-channel')
        .listen('.click-event', (event) => {
            console.log('Event received:', event.message);
            buttonColor.value = 'bg-green-500'; // Change to green on event
        });
});

onUnmounted(() => {
    window.Echo.leave('click-channel');
});
</script>

<template>
    <div>
        <h1>Escuchar Page</h1>
        <button :class="[buttonColor, 'text-white px-4 py-2']">
            Botón que cambia de color
        </button>
    </div>
</template>