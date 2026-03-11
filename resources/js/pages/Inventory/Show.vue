<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    BuildingStorefrontIcon,
    CubeIcon,
    ArrowPathIcon,
    ChevronLeftIcon,
    MagnifyingGlassIcon,
    ExclamationTriangleIcon,
    DocumentArrowDownIcon,
    PencilSquareIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    store: {
        id: number
        name: string
        code: string
        location: string
        branch: {
            id: number
            name: string
        }
    }
    inventory: Array<{
        id: number
        product_id: number
        product_name: string
        product_sku: string
        quantity: number
        reserved_quantity: number
        available_quantity: number
        reorder_point: number
        is_low_stock: boolean
        unit_price: number
        total_value: number
    }>
}>()

// Search state
const searchQuery = ref('')
const statusFilter = ref<'all' | 'low' | 'out'>('all')

// Filtered inventory based on search
const filteredInventory = computed(() => {
    let filtered = props.inventory

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        filtered = filtered.filter(item => 
            item.product_name.toLowerCase().includes(query) ||
            item.product_sku.toLowerCase().includes(query)
        )
    }

    // Apply status filter
    if (statusFilter.value === 'low') {
        filtered = filtered.filter(item => item.is_low_stock && item.available_quantity > 0)
    } else if (statusFilter.value === 'out') {
        filtered = filtered.filter(item => item.available_quantity <= 0)
    }

    return filtered
})

// Summary statistics
const summary = computed(() => {
    const totalItems = props.inventory.length
    const totalValue = props.inventory.reduce((sum, item) => sum + item.total_value, 0)
    const lowStockCount = props.inventory.filter(item => item.is_low_stock && item.available_quantity > 0).length
    const outOfStockCount = props.inventory.filter(item => item.available_quantity <= 0).length
    
    return {
        totalItems,
        totalValue,
        lowStockCount,
        outOfStockCount
    }
})

// Format currency
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value)
}

// Navigation
const goBack = () => {
    router.visit('/inventory')
}

const goToAdjustment = (productId?: number) => {
    const url = productId 
        ? `/inventory/adjustment/create?store_id=${props.store.id}&product_id=${productId}`
        : `/inventory/adjustment/create?store_id=${props.store.id}`
    router.visit(url)
}

const exportInventory = () => {
    window.location.href = `/inventory/export?store_id=${props.store.id}`
}

// Get stock status
const getStockStatus = (item: any) => {
    if (item.available_quantity <= 0) return 'out'
    if (item.is_low_stock) return 'low'
    return 'ok'
}

const getStatusBadgeClass = (status: string) => {
    const classes = {
        ok: 'bg-green-100 text-green-800',
        low: 'bg-yellow-100 text-yellow-800',
        out: 'bg-red-100 text-red-800'
    }
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

const getStatusLabel = (status: string) => {
    const labels = {
        ok: 'In Stock',
        low: 'Low Stock',
        out: 'Out of Stock'
    }
    return labels[status as keyof typeof labels] || status
}
</script>

<template>
    <Head :title="`${store.name} - Inventory`" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header with back button -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button
                        @click="goBack"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        <ChevronLeftIcon class="w-5 h-5" />
                    </button>
                    <div>
                        <div class="flex items-center space-x-3">
                            <BuildingStorefrontIcon class="w-6 h-6 text-indigo-600" />
                            <h1 class="text-2xl font-bold text-gray-900">{{ store.name }}</h1>
                        </div>
                        <p class="text-gray-600 mt-1">
                            Branch: {{ store.branch?.name }} | Code: {{ store.code }}
                        </p>
                    </div>
                </div>
                
                <!-- Action buttons -->
                <div class="flex space-x-3">
                    <button
                        @click="exportInventory"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5 mr-2" />
                        Export
                    </button>
                    <button
                        @click="goToAdjustment()"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center"
                    >
                        <ArrowPathIcon class="w-5 h-5 mr-2" />
                        Adjust Stock
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900">{{ summary.totalItems }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Value</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.totalValue) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Low Stock</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ summary.lowStockCount }}</p>
                        </div>
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <ExclamationTriangleIcon class="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Out of Stock</p>
                            <p class="text-2xl font-bold text-red-600">{{ summary.outOfStockCount }}</p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Search Products
                        </label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by name or SKU..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Status
                        </label>
                        <select
                            v-model="statusFilter"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="all">All Items</option>
                            <option value="low">Low Stock Only</option>
                            <option value="out">Out of Stock</option>
                        </select>
                    </div>
                </div>

                <!-- Results count -->
                <div class="mt-2 text-sm text-gray-500">
                    Showing {{ filteredInventory.length }} of {{ inventory.length }} items
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    SKU
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    On Hand
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Reserved
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Available
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unit Price
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Value
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in filteredInventory" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ item.product_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ item.product_sku }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-orange-600">
                                    {{ item.reserved_quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="{
                                        'text-green-600': item.available_quantity > item.reorder_point,
                                        'text-yellow-600': item.is_low_stock && item.available_quantity > 0,
                                        'text-red-600': item.available_quantity <= 0
                                    }"
                                >
                                    {{ item.available_quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatCurrency(item.unit_price) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                                    {{ formatCurrency(item.total_value) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        :class="['px-2 py-1 text-xs rounded-full', getStatusBadgeClass(getStockStatus(item))]"
                                    >
                                        {{ getStatusLabel(getStockStatus(item)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button
                                        @click="goToAdjustment(item.product_id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="Adjust Stock"
                                    >
                                        <PencilSquareIcon class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Empty State -->
                            <tr v-if="filteredInventory.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center">
                                    <CubeIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No inventory items found</h3>
                                    <p class="text-gray-500">
                                        {{ searchQuery ? 'Try adjusting your search' : 'This store has no inventory' }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div v-if="summary.lowStockCount > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                    <ExclamationTriangleIcon class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" />
                    <div class="text-sm text-yellow-800">
                        <p class="font-medium">
                            {{ summary.lowStockCount }} item(s) are low on stock
                        </p>
                        <p class="mt-1">Consider reordering soon to avoid stockouts.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>