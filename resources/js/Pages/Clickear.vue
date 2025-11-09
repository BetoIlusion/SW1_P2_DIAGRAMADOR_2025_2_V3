<script setup>
import { router } from '@inertiajs/vue3'

const handleClick = () => {
    router.post('/trigger-click', {}, {
        onSuccess: () => {
            console.log('Event triggered!');
        },
    });
};
</script>
<template>
    <div>
        <h1>Clickear Page</h1>
        <button @click="handleClick" class="bg-blue-500 text-white px-4 py-2">
            Hacer Click
        </button>
    </div>
</template>