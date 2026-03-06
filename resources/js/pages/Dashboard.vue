<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import { 
    CurrencyDollarIcon, 
    ArrowPathIcon, 
    CubeIcon, 
    ExclamationTriangleIcon 
} from '@heroicons/vue/24/outline'

const toast = useToast()

interface DashboardStats {
    todaySales: number
    pendingTransfers: number
    lowStockCount: number
    inventoryValue: number
    recentSales: Array<{
        id: number
        sale_number: string
        grand_total: number
        created_at: string
    }>
    lowStockItems: Array<{
        id: number
        product_name: string
        store_name: string
        quantity: number
        reorder_point: number
    }>
}

const stats = ref<DashboardStats>({
    todaySales: 0,
    pendingTransfers: 0,
    lowStockCount: 0,
    inventoryValue: 0,
    recentSales: [],
    lowStockItems: []
})

const loading = ref(true)

const fetchDashboardData = async () => {
    try {
        loading.value = true
        const response = await axios.get('/api/dashboard/stats')
        stats.value = response.data
    } catch (error) {
        toast.error('Failed to load dashboard data')
        console.error(error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchDashboardData()
})
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Welcome Section -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-600">Welcome back! Here's what's happening today.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Today's Sales -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Today's Sales</p>
                            <p class="text-2xl font-bold text-gray-900">${{ stats.todaySales.toFixed(2) }}</p>
                        </div>
                        <div class="p-3 bg-indigo-100 rounded-full">
                            <CurrencyDollarIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                    </div>
                </div>

                <!-- Pending Transfers -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Pending Transfers</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.pendingTransfers }}</p>
                        </div>
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <ArrowPathIcon class="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </div>

                <!-- Low Stock Items -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Low Stock Items</p>
                            <p class="text-2xl font-bold text-gray-900">{{ stats.lowStockCount }}</p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </div>

                <!-- Inventory Value -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Inventory Value</p>
                            <p class="text-2xl font-bold text-gray-900">${{ stats.inventoryValue.toFixed(2) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Sales & Low Stock Alerts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Sales -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Sales</h3>
                    </div>
                    <div class="p-6">
                        <div v-if="stats.recentSales.length === 0" class="text-center text-gray-500 py-4">
                            No recent sales
                        </div>
                        <div v-else class="space-y-4">
                            <div 
                                v-for="sale in stats.recentSales" 
                                :key="sale.id"
                                class="flex items-center justify-between py-2 border-b last:border-0"
                            >
                                <div>
                                    <p class="font-medium text-gray-900">{{ sale.sale_number }}</p>
                                    <p class="text-sm text-gray-500">{{ new Date(sale.created_at).toLocaleDateString() }}</p>
                                </div>
                                <p class="font-semibold text-indigo-600">${{ sale.grand_total.toFixed(2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Low Stock Alerts</h3>
                    </div>
                    <div class="p-6">
                        <div v-if="stats.lowStockItems.length === 0" class="text-center text-gray-500 py-4">
                            No low stock items
                        </div>
                        <div v-else class="space-y-4">
                            <div 
                                v-for="item in stats.lowStockItems" 
                                :key="item.id"
                                class="flex items-center justify-between py-2 border-b last:border-0"
                            >
                                <div>
                                    <p class="font-medium text-gray-900">{{ item.product_name }}</p>
                                    <p class="text-sm text-gray-500">{{ item.store_name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-red-600">{{ item.quantity }} left</p>
                                    <p class="text-xs text-gray-500">Reorder at: {{ item.reorder_point }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>