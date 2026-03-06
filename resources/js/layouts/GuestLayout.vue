<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col justify-center items-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/">
                <h1 class="text-3xl font-bold text-center text-indigo-600">KK Wholesalers</h1>
            </Link>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <slot />
            </div>
        </div>
    </div>
</template>