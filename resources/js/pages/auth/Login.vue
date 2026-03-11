<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import TextLink from '@/Components/TextLink.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { ref } from 'vue';

// Define props
defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

// Create form using Inertia's useForm
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Submit handler
const submit = () => {
    form.post('/login', {
        preserveState: true,
        onSuccess: () => {
            // Redirect handled by server
        },
    });
};
</script>

<template>
    <AuthLayout
        title="Log in to your  KK Wholesalers account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <!-- Status Message (e.g., after password reset) -->
        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <!-- Login Form -->
        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <!-- Email Field -->
                <div class="grid gap-2">
                    <label for="email" class="text-sm font-medium text-gray-700">
                        Email address
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <label for="password" class="text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <TextLink
                            v-if="canResetPassword"
                            href="/forgot-password"
                            class="text-sm text-indigo-600 hover:text-indigo-500"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        :tabindex="3"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label for="remember" class="ml-2 block text-sm text-gray-900">
                        Remember me
                    </label>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    :tabindex="4"
                    :disabled="form.processing"
                >
                    <svg 
                        v-if="form.processing" 
                        class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" 
                        xmlns="http://www.w3.org/2000/svg" 
                        fill="none" 
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? 'Logging in...' : 'Log in' }}
                </button>
            </div>

            <!-- Register Link -->
            <div
                v-if="canRegister"
                class="text-center text-sm text-gray-600"
            >
                Don't have an account?
                <Link 
                    href="/register" 
                    class="text-indigo-600 hover:text-indigo-500 font-medium"
                    :tabindex="5"
                >
                    Sign up
                </Link>
            </div>
        </form>
    </AuthLayout>
</template>