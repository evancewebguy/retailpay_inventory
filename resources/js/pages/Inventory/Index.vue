<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import {
    CubeIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    PencilSquareIcon,
    DocumentArrowDownIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

// Define props from the controller
const props = defineProps<{
    inventory: {
        data: Array<{
            id: number
            store_id: number
            product_id: number
            quantity: number
            reserved_quantity: number
            available_quantity: number
            reorder_point: number
            product: {
                id: number
                name: string
                sku: string
                selling_price: number
                cost_price: number
            }
            store: {
                id: number
                name: string
                code: string
                branch: {
                    id: number
                    name: string
                }
            }
        }>
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
    stores: Array<{
        id: number
        name: string
        code: string
    }>
    branches: Array<{
        id: number
        name: string
        code: string
    }>
    filters: {
        store_id?: string
        branch_id?: string
        search?: string
        low_stock?: string
        per_page?: number
    }
}>()

// Local state for filters
const search = ref(props.filters.search || '')
const storeId = ref(props.filters.store_id || '')
const branchId = ref(props.filters.branch_id || '')
const lowStock = ref(props.filters.low_stock || '')
const perPage = ref(props.filters.per_page || 20)

// Loading state for actions
const loading = ref(false)
const selectedItems = ref<number[]>([])

// Watch for filter changes and update URL
watch([search, storeId, branchId, lowStock, perPage], () => {
    handleFilterChange()
})

// Debounce search input
let searchTimeout: ReturnType<typeof setTimeout>
const handleFilterChange = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/inventory', {
            search: search.value,
            store_id: storeId.value,
            branch_id: branchId.value,
            low_stock: lowStock.value,
            per_page: perPage.value,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 500)
}

// Clear all filters
const clearFilters = () => {
    search.value = ''
    storeId.value = ''
    branchId.value = ''
    lowStock.value = ''
    perPage.value = 20
}

// Navigate to adjustment page
const goToAdjustment = () => {
    router.get('/inventory/adjustment/create')
}

// View store inventory
const viewStoreInventory = (storeId: number) => {
    router.get(`/inventory/${storeId}`)
}

// Export inventory report
const exportInventory = () => {
    window.location.href = '/inventory/export/report?' + new URLSearchParams({
        store_id: storeId.value,
        branch_id: branchId.value,
        search: search.value,
        low_stock: lowStock.value,
    }).toString()
}

// Toggle select all
const toggleSelectAll = () => {
    if (selectedItems.value.length === props.inventory.data.length) {
        selectedItems.value = []
    } else {
        selectedItems.value = props.inventory.data.map(item => item.id)
    }
}

// Toggle select item
const toggleSelectItem = (id: number) => {
    if (selectedItems.value.includes(id)) {
        selectedItems.value = selectedItems.value.filter(itemId => itemId !== id)
    } else {
        selectedItems.value.push(id)
    }
}

// Format currency
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(value)
}

// Get stock status
const getStockStatus = (item: any) => {
    const available = item.quantity - item.reserved_quantity
    if (available <= 0) return 'out_of_stock'
    if (available <= item.reorder_point) return 'low_stock'
    return 'in_stock'
}

// Get status badge class
const getStatusBadgeClass = (status: string) => {
    const classes = {
        in_stock: 'bg-green-100 text-green-800',
        low_stock: 'bg-yellow-100 text-yellow-800',
        out_of_stock: 'bg-red-100 text-red-800'
    }
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

// Get status label
const getStatusLabel = (status: string) => {
    const labels = {
        in_stock: 'In Stock',
        low_stock: 'Low Stock',
        out_of_stock: 'Out of Stock'
    }
    return labels[status as keyof typeof labels] || status
}

// Pagination
const goToPage = (page: number) => {
    router.get('/inventory', {
        ...props.filters,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Inventory Management" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Inventory Management</h1>
                    <p class="text-gray-600">Track and manage stock across all stores</p>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="exportInventory"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5 mr-2" />
                        Export
                    </button>
                    <button
                        @click="goToAdjustment"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center"
                    >
                        <ArrowPathIcon class="w-5 h-5 mr-2" />
                        Adjust Stock
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <!-- Search -->
                    <div class="col-span-1 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Search
                        </label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search by product name or SKU..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Branch Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Branch
                        </label>
                        <select
                            v-model="branchId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Branches</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Store Filter -->
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

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Status
                        </label>
                        <select
                            v-model="lowStock"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Items</option>
                            <option value="true">Low Stock Only</option>
                        </select>
                    </div>
                </div>

                <!-- Active Filters -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Active filters:</span>
                        <span v-if="search" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            Search: {{ search }}
                        </span>
                        <span v-if="branchId" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            Branch selected
                        </span>
                        <span v-if="storeId" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            Store selected
                        </span>
                        <span v-if="lowStock" class="inline-flex items-center px-2 py-1 bg-yellow-100 text-sm rounded-md">
                            Low stock only
                        </span>
                        <span v-if="!search && !branchId && !storeId && !lowStock" class="text-sm text-gray-400">
                            None
                        </span>
                    </div>
                    <button
                        @click="clearFilters"
                        class="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        Clear all
                    </button>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <!-- Per Page Selector -->
                <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-700">Show</label>
                        <select
                            v-model="perPage"
                            class="border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="text-sm text-gray-700">entries</span>
                    </div>
                    <div class="text-sm text-gray-700">
                        Showing {{ inventory.from }} to {{ inventory.to }} of {{ inventory.total }} results
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input
                                        type="checkbox"
                                        :checked="selectedItems.length === inventory.data.length && inventory.data.length > 0"
                                        @change="toggleSelectAll"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Store / Branch
                                </th>
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
                            <tr v-for="item in inventory.data" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        :checked="selectedItems.includes(item.id)"
                                        @change="toggleSelectItem(item.id)"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ item.store?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ item.store?.branch?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ item.product?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ item.product?.sku }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-orange-600">
                                    {{ item.reserved_quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="{
                                        'text-green-600': item.quantity - item.reserved_quantity > item.reorder_point,
                                        'text-yellow-600': item.quantity - item.reserved_quantity <= item.reorder_point && item.quantity - item.reserved_quantity > 0,
                                        'text-red-600': item.quantity - item.reserved_quantity <= 0
                                    }"
                                >
                                    {{ item.quantity - item.reserved_quantity }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatCurrency(item.product?.selling_price || 0) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                                    {{ formatCurrency((item.quantity - item.reserved_quantity) * (item.product?.selling_price || 0)) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        :class="['px-2 py-1 text-xs rounded-full', getStatusBadgeClass(getStockStatus(item))]"
                                    >
                                        {{ getStatusLabel(getStockStatus(item)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    <button
                                        @click="viewStoreInventory(item.store_id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="View Store Inventory"
                                    >
                                        <EyeIcon class="w-5 h-5" />
                                    </button>
                                    <Link
                                        :href="`/inventory/adjustment/create?store_id=${item.store_id}&product_id=${item.product_id}`"
                                        class="text-yellow-600 hover:text-yellow-900"
                                        title="Adjust Stock"
                                    >
                                        <PencilSquareIcon class="w-5 h-5" />
                                    </Link>
                                </td>
                            </tr>
                            
                            <!-- Empty State -->
                            <tr v-if="inventory.data.length === 0">
                                <td colspan="11" class="px-6 py-12 text-center">
                                    <CubeIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No inventory items found</h3>
                                    <p class="text-gray-500 mb-4">Try adjusting your filters or add new products</p>
                                    <button
                                        @click="clearFilters"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                    >
                                        Clear Filters
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="inventory.last_page > 1" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Page {{ inventory.current_page }} of {{ inventory.last_page }}
                    </div>
                    <div class="flex space-x-2">
                        <button
                            @click="goToPage(1)"
                            :disabled="inventory.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            First
                        </button>
                        <button
                            @click="goToPage(inventory.current_page - 1)"
                            :disabled="inventory.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Previous
                        </button>
                        <span class="px-3 py-1 text-sm">
                            {{ inventory.current_page }}
                        </span>
                        <button
                            @click="goToPage(inventory.current_page + 1)"
                            :disabled="inventory.current_page === inventory.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Next
                        </button>
                        <button
                            @click="goToPage(inventory.last_page)"
                            :disabled="inventory.current_page === inventory.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Last
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900">{{ inventory.total }}</p>
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
                            <p class="text-2xl font-bold text-gray-900">
                                {{ formatCurrency(inventory.data.reduce((sum, item) => 
                                    sum + (item.quantity * (item.product?.selling_price || 0)), 0
                                )) }}
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Low Stock Items</p>
                            <p class="text-2xl font-bold text-yellow-600">
                                {{ inventory.data.filter(item => 
                                    item.quantity - item.reserved_quantity <= item.reorder_point && 
                                    item.quantity - item.reserved_quantity > 0
                                ).length }}
                            </p>
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
                            <p class="text-2xl font-bold text-red-600">
                                {{ inventory.data.filter(item => 
                                    item.quantity - item.reserved_quantity <= 0
                                ).length }}
                            </p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>