<script setup>
const { price } = defineProps({
    price: Number,
});
</script>

<template>
    <span class="p-px font-bold text-green-600">$ {{ price.toFixed(2) }}</span>
</template>
