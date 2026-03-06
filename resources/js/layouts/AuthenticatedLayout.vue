<script setup lang="ts">
import { ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { 
    HomeIcon, 
    ShoppingCartIcon, 
    ArrowPathIcon, 
    CubeIcon, 
    ChartBarIcon,
    UsersIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    BellIcon,
    ChevronDownIcon
} from '@heroicons/vue/24/outline'
import type { User } from '@/types'

const page = usePage()
const user = page.props.auth?.user as User | undefined

const isSidebarOpen = ref(true)
const isProfileMenuOpen = ref(false)

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
    { name: 'Sales', href: '/sales', icon: ShoppingCartIcon },
    { name: 'Transfers', href: '/transfers', icon: ArrowPathIcon },
    { name: 'Inventory', href: '/inventory', icon: CubeIcon },
    { name: 'Reports', href: '/reports', icon: ChartBarIcon },
]

// Add admin routes if user is admin
if (user?.roles?.includes('Administrator')) {
    navigation.push(
        { name: 'Users', href: '/users', icon: UsersIcon },
        { name: 'Settings', href: '/settings', icon: Cog6ToothIcon }
    )
}

const logout = () => {
    router.post('/logout')
}

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value
}

// Close dropdown when clicking outside
const dropdownRef = ref<HTMLElement | null>(null)

const handleClickOutside = (event: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
        isProfileMenuOpen.value = false
    }
}

// Add click outside listener
if (typeof window !== 'undefined') {
    window.addEventListener('click', handleClickOutside)
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Sidebar -->
        <aside 
            :class="[
                'fixed top-0 left-0 z-40 h-screen transition-transform bg-white border-r border-gray-200',
                isSidebarOpen ? 'w-64' : 'w-20',
                'hidden sm:block'
            ]"
        >
            <div class="h-full px-3 py-4 overflow-y-auto">
                <!-- Logo -->
                <div class="flex items-center justify-between mb-6 px-2">
                    <Link href="/dashboard" class="flex items-center">
                        <span v-if="isSidebarOpen" class="text-xl font-bold text-indigo-600">KK Wholesalers</span>
                        <span v-else class="text-2xl font-bold text-indigo-600">KKW</span>
                    </Link>
                    <button @click="toggleSidebar" class="p-1 rounded-lg hover:bg-gray-100">
                        <ChevronDownIcon class="w-5 h-5" :class="{ 'rotate-180': !isSidebarOpen }" />
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="space-y-2">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.href"
                        class="flex items-center px-2 py-2 text-gray-700 rounded-lg hover:bg-gray-100 group"
                        :class="{ 'bg-indigo-50 text-indigo-700': $page.url === item.href }"
                    >
                        <component :is="item.icon" class="w-6 h-6" :class="{ 'mr-3': isSidebarOpen }" />
                        <span v-if="isSidebarOpen">{{ item.name }}</span>
                    </Link>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <div :class="['transition-all', isSidebarOpen ? 'sm:ml-64' : 'sm:ml-20']">
            <!-- Top Navigation -->
            <nav class="bg-white border-b border-gray-200">
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between">
                        <!-- Mobile menu button -->
                        <button @click="isSidebarOpen = !isSidebarOpen" class="sm:hidden p-2 rounded-lg hover:bg-gray-100">
                            <ChevronDownIcon class="w-5 h-5" />
                        </button>

                        <!-- Right side items -->
                        <div class="flex items-center space-x-4 ml-auto">
                            <!-- Notifications -->
                            <button class="p-2 rounded-lg hover:bg-gray-100 relative">
                                <BellIcon class="w-5 h-5 text-gray-600" />
                                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                            </button>

                            <!-- Profile dropdown -->
                            <div class="relative" ref="dropdownRef">
                                <button 
                                    @click.stop="isProfileMenuOpen = !isProfileMenuOpen"
                                    class="flex items-center space-x-2 focus:outline-none"
                                >
                                    <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                                        {{ user?.name?.charAt(0) || 'U' }}
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 hidden md:block">
                                        {{ user?.name || 'User' }}
                                    </span>
                                    <ChevronDownIcon class="w-4 h-4 text-gray-500" />
                                </button>

                                <!-- Dropdown menu -->
                                <div 
                                    v-if="isProfileMenuOpen"
                                    class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50 border border-gray-200"
                                >
                                    <Link 
                                        href="/profile" 
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        @click="isProfileMenuOpen = false"
                                    >
                                        Your Profile
                                    </Link>
                                    <button 
                                        @click="logout"
                                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100 flex items-center"
                                    >
                                        <ArrowRightOnRectangleIcon class="w-4 h-4 mr-2" />
                                        Logout
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="p-6">
                <slot />
            </main>
        </div>
    </div>
</template>