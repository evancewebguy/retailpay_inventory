<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    CurrencyDollarIcon,
    ArrowPathIcon,
    CubeIcon,
    ExclamationTriangleIcon,
    ChartBarIcon,
    ShoppingCartIcon,
    TruckIcon,
    DocumentArrowDownIcon,
    CalendarIcon,
    ChevronLeftIcon,
    BuildingStorefrontIcon,
    ChevronRightIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    filters: {
        from_date: string
        to_date: string
        store_id?: string
    }
    stores: Array<{
        id: number
        name: string
        code: string
    }>
    summary: {
        total_sales: number
        total_movements: number
        total_products: number
        total_stores: number
        low_stock_count: number
        inventory_value: number
    }
    sales_overview: Array<{
        date: string
        count: number
        total: number
    }>
    top_products: Array<{
        id: number
        name: string
        sku: string
        total_quantity: number
        total_revenue: number
    }>
    movement_types: Array<{
        movement_type: string
        count: number
        total_quantity: number
    }>
    recent_movements: Array<{
        id: number
        reference: string
        type: string
        product: {
            id: number
            name: string
            sku: string
        } | null
        quantity: number
        from_store: {
                id: number
                name: string
                code: string
            } | null
        to_store: {
                id: number
                name: string
                code: string
            } | null
        created_at: string
        created_by: string
    }>
    store_performance: Array<{
        id: number
        name: string
        code: string
        total_sales: number
        total_revenue: number
        average_sale_value: number
    }>
    date_range: {
        from: string
        to: string
    }
    generated_at: string
}>()

// Local filter state
const fromDate = ref(props.filters.from_date)
const toDate = ref(props.filters.to_date)
const storeId = ref(props.filters.store_id || '')

// Date range picker visibility
const showDatePicker = ref(false)

// Format currency
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
    }).format(value)
}

// Format number
const formatNumber = (value: number) => {
    return new Intl.NumberFormat('en-US').format(value)
}

// Format date
const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

// Format datetime
const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Get movement type label
const getMovementTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        'SALE': 'Sale',
        'TRANSFER': 'Transfer',
        'ADJUSTMENT': 'Adjustment',
        'PROCUREMENT': 'Procurement',
        'RETURN': 'Return',
        'DAMAGE': 'Damage',
        'LOST': 'Lost'
    }
    return labels[type] || type
}

// Get movement type color
const getMovementTypeColor = (type: string) => {
    const colors: Record<string, string> = {
        'SALE': 'bg-green-100 text-green-800',
        'TRANSFER': 'bg-blue-100 text-blue-800',
        'ADJUSTMENT': 'bg-yellow-100 text-yellow-800',
        'PROCUREMENT': 'bg-purple-100 text-purple-800',
        'RETURN': 'bg-gray-100 text-gray-800',
        'DAMAGE': 'bg-red-100 text-red-800',
        'LOST': 'bg-orange-100 text-orange-800'
    }
    return colors[type] || 'bg-gray-100 text-gray-800'
}

// Apply filters
const applyFilters = () => {
    router.get('/reports', {
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || undefined
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

// Reset filters
const resetFilters = () => {
    fromDate.value = props.date_range.from
    toDate.value = props.date_range.to
    storeId.value = ''
    applyFilters()
}

// Export report
const exportReport = () => {
    window.location.href = '/reports/export?type=summary&' + new URLSearchParams({
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || ''
    }).toString()
}

// Navigate to specific report
const goToReport = (path: string) => {
    router.get(path)
}

// Calculate sales chart max for scaling
const salesChartMax = computed(() => {
    const max = Math.max(...props.sales_overview.map(d => d.total), 0)
    return max || 1
})
</script>

<template>
    <Head title="Reports Dashboard" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Reports Dashboard</h1>
                    <p class="text-gray-600">Comprehensive business intelligence and analytics</p>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="exportReport"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5 mr-2" />
                        Export
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            From Date
                        </label>
                        <input
                            v-model="fromDate"
                            type="date"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            To Date
                        </label>
                        <input
                            v-model="toDate"
                            type="date"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Store
                        </label>
                        <select
                            v-model="storeId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Stores</option>
                            <option v-for="store in stores" :key="store.id" :value="store.id">
                                {{ store.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button
                            @click="applyFilters"
                            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700"
                        >
                            Apply
                        </button>
                        <button
                            @click="resetFilters"
                            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700"
                        >
                            Reset
                        </button>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500 text-right">
                    Generated: {{ formatDateTime(generated_at) }}
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Total Sales -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Sales</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.total_sales) }}</p>
                        </div>
                        <div class="p-3 bg-indigo-100 rounded-full">
                            <ShoppingCartIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                    </div>
                </div>

                <!-- Total Movements -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Inventory Movements</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.total_movements) }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <ArrowPathIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>

                <!-- Inventory Value -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Inventory Value</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.inventory_value) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <CurrencyDollarIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>

                <!-- Total Products -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Products</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.total_products) }}</p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-purple-600" />
                        </div>
                    </div>
                </div>

                <!-- Total Stores -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Stores</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summary.total_stores) }}</p>
                        </div>
                        <div class="p-3 bg-teal-100 rounded-full">
                            <BuildingStorefrontIcon class="w-6 h-6 text-teal-600" />
                        </div>
                    </div>
                </div>

                <!-- Low Stock Items -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Low Stock Items</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ formatNumber(summary.low_stock_count) }}</p>
                        </div>
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <ExclamationTriangleIcon class="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Navigation Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Link href="/reports/stock-valuation" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-indigo-100 rounded-lg">
                            <CubeIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Stock Valuation</h3>
                            <p class="text-sm text-gray-500">View inventory value by store</p>
                        </div>
                    </div>
                </Link>

                <Link href="/reports/movement-history" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <ArrowPathIcon class="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Movement History</h3>
                            <p class="text-sm text-gray-500">Track all inventory movements</p>
                        </div>
                    </div>
                </Link>

                <Link href="/reports/sales" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <ShoppingCartIcon class="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Sales Report</h3>
                            <p class="text-sm text-gray-500">Analyze sales performance</p>
                        </div>
                    </div>
                </Link>

                <Link href="/reports/products" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <ChartBarIcon class="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Product Performance</h3>
                            <p class="text-sm text-gray-500">Best selling products</p>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Sales Overview Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Sales Overview</h2>
                <div class="h-64">
                    <div class="flex h-48 items-end space-x-2">
                        <div v-for="(day, index) in sales_overview" :key="index" class="flex-1 flex flex-col items-center">
                            <div class="w-full bg-indigo-100 rounded-t relative group">
                                <div 
                                    class="bg-indigo-600 rounded-t transition-all duration-300"
                                    :style="{ height: `${(day.total / salesChartMax) * 100}%` }"
                                >
                                    <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap z-10">
                                        {{ formatCurrency(day.total) }} ({{ day.count }} sales)
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs text-gray-500 mt-2">{{ formatDate(day.date) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top Products -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Top Products</h2>
                    <div class="space-y-4">
                        <div v-for="product in top_products" :key="product.id" class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-gray-900">{{ product.name }}</p>
                                <p class="text-xs text-gray-500">SKU: {{ product.sku }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-indigo-600">{{ formatNumber(product.total_quantity) }} units</p>
                                <p class="text-xs text-gray-500">{{ formatCurrency(product.total_revenue) }}</p>
                            </div>
                        </div>
                        <div v-if="top_products.length === 0" class="text-center text-gray-500 py-4">
                            No sales data available
                        </div>
                    </div>
                    <div class="mt-4 text-right">
                        <Link href="/reports/products" class="text-sm text-indigo-600 hover:text-indigo-900">
                            View All Products →
                        </Link>
                    </div>
                </div>

                <!-- Movement Types Breakdown -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Movement Types</h2>
                    <div class="space-y-4">
                        <div v-for="type in movement_types" :key="type.movement_type" class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span :class="['px-2 py-1 text-xs rounded-full', getMovementTypeColor(type.movement_type)]">
                                    {{ getMovementTypeLabel(type.movement_type) }}
                                </span>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-900">{{ formatNumber(type.count) }} movements</p>
                                <p class="text-xs text-gray-500">{{ formatNumber(Math.abs(type.total_quantity)) }} units</p>
                            </div>
                        </div>
                        <div v-if="movement_types.length === 0" class="text-center text-gray-500 py-4">
                            No movement data available
                        </div>
                    </div>
                    <div class="mt-4 text-right">
                        <Link href="/reports/movement-history" class="text-sm text-indigo-600 hover:text-indigo-900">
                            View All Movements →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Recent Movements -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Inventory Movements</h2>
                    <Link href="/reports/movement-history" class="text-sm text-indigo-600 hover:text-indigo-900">
                        View All
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="movement in recent_movements" :key="movement.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDateTime(movement.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ movement.reference }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2 py-1 text-xs rounded-full', getMovementTypeColor(movement.type)]">
                                        {{ getMovementTypeLabel(movement.type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ movement.product?.name || 'N/A' }} ({{ movement.product?.sku || 'N/A' }})    
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="movement.quantity > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ movement.from_store?.name || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ movement.to_store?.name || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ movement.created_by }}
                                </td>
                            </tr>
                            <tr v-if="recent_movements.length === 0">
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    No recent movements
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Store Performance -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Store Performance</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Sale Value</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="store in store_performance" :key="store.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ store.name }}</div>
                                    <div class="text-xs text-gray-500">Code: {{ store.code }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatNumber(store.total_sales) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-indigo-600">
                                    {{ formatCurrency(store.total_revenue) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatCurrency(store.average_sale_value) }}
                                </td>
                            </tr>
                            <tr v-if="store_performance.length === 0">
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No store performance data available
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>