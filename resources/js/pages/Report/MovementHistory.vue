<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    ArrowPathIcon,
    DocumentArrowDownIcon,
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    FunnelIcon,
    CalendarIcon,
    BuildingStorefrontIcon,
    CubeIcon,
    UserIcon,
    TruckIcon,
    ShoppingCartIcon,
    AdjustmentsHorizontalIcon,
    ArchiveBoxArrowDownIcon,
    ArrowUturnLeftIcon,
    ExclamationTriangleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    filters: {
        from_date: string
        to_date: string
        store_id?: string
        product_id?: string
        movement_type?: string
        per_page: number
        page: number
    }
    stores: Array<{
        id: number
        name: string
        code: string
    }>
    products: Array<{
        id: number
        name: string
        sku: string
    }>
    movement_types: Array<{
        value: string
        label: string
    }>
    report_data: { 
        data: Array<{
            id: number
            reference: string
            type: string
            product:  {
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
        summary: {
            total_movements: number
            total_quantity: number
            by_type: Record<string, {
                count: number
                total_quantity: number
            }>
        }
        pagination: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
    }
    generated_at: string
}>()

// Local filter state
const fromDate = ref(props.filters.from_date)
const toDate = ref(props.filters.to_date)
const storeId = ref(props.filters.store_id || '')
const productId = ref(props.filters.product_id || '')
const movementType = ref(props.filters.movement_type || '')
const perPage = ref(props.filters.per_page)
const currentPage = ref(props.report_data.pagination.current_page)

// Search state
const searchQuery = ref('')
const showFilters = ref(true)

// Filtered data based on search
const filteredData = computed(() => {
    if (!searchQuery.value) return props.report_data.data
    
    const query = searchQuery.value.toLowerCase()
    return props.report_data.data.filter(movement => 
        movement.reference.toLowerCase().includes(query) ||
        movement.product?.name.toLowerCase().includes(query) ||
        movement.product?.sku.toLowerCase().includes(query) ||
        movement.from_store?.name.toLowerCase().includes(query) ||
        movement.to_store?.name.toLowerCase().includes(query) ||
        movement.created_by.toLowerCase().includes(query)
    )
})

// Format currency (not used but keeping for consistency)
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

// Get movement type icon
const getMovementTypeIcon = (type: string) => {
    const icons: Record<string, any> = {
        'SALE': ShoppingCartIcon,
        'TRANSFER': TruckIcon,
        'ADJUSTMENT': AdjustmentsHorizontalIcon,
        'PROCUREMENT': ArchiveBoxArrowDownIcon,
        'RETURN': ArrowUturnLeftIcon,
        'DAMAGE': ExclamationTriangleIcon,
        'LOST': XCircleIcon
    }
    return icons[type] || CubeIcon
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

// Get quantity color class
const getQuantityColorClass = (quantity: number) => {
    if (quantity > 0) return 'text-green-600'
    if (quantity < 0) return 'text-red-600'
    return 'text-gray-600'
}

// Apply filters
const applyFilters = () => {
    router.get('/reports/movement-history', {
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || undefined,
        product_id: productId.value || undefined,
        movement_type: movementType.value || undefined,
        per_page: perPage.value
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

// Reset filters
const resetFilters = () => {
    fromDate.value = props.filters.from_date
    toDate.value = props.filters.to_date
    storeId.value = ''
    productId.value = ''
    movementType.value = ''
    perPage.value = 50
    searchQuery.value = ''
    applyFilters()
}

// Export report
const exportReport = () => {
    window.location.href = '/reports/export?type=movements&' + new URLSearchParams({
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || '',
        product_id: productId.value || '',
        movement_type: movementType.value || ''
    }).toString()
}

// Go to page
const goToPage = (page: number) => {
    if (page < 1 || page > props.report_data.pagination.last_page) return
    
    router.get('/reports/movement-history', {
        ...props.filters,
        page
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

// Toggle filters
const toggleFilters = () => {
    showFilters.value = !showFilters.value
}
</script>

<template>
    <Head title="Movement History Report" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        href="/reports"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        <ChevronLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Movement History Report</h1>
                        <p class="text-gray-600">Track all inventory movements across stores</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="toggleFilters"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <FunnelIcon class="w-5 h-5 mr-2" />
                        {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                    </button>
                    <button
                        @click="exportReport"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5 mr-2" />
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Movements</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.summary.total_movements) }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <ArrowPathIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Quantity Moved</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.summary.total_quantity) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Movement Types Summary -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Movements by Type</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="(data, type) in report_data.summary.by_type" :key="type" 
                         class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center space-x-3">
                            <div :class="['p-2 rounded-lg', getMovementTypeColor(type)]">
                                <component :is="getMovementTypeIcon(type)" class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ getMovementTypeLabel(type) }}</p>
                                <p class="text-xs text-gray-500">{{ data.count }} movements</p>
                                <p class="text-xs font-medium" :class="getQuantityColorClass(data.total_quantity)">
                                    {{ data.total_quantity > 0 ? '+' : '' }}{{ formatNumber(data.total_quantity) }} units
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div v-if="showFilters" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            From Date
                        </label>
                        <div class="relative">
                            <CalendarIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                v-model="fromDate"
                                type="date"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            To Date
                        </label>
                        <div class="relative">
                            <CalendarIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                v-model="toDate"
                                type="date"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Store Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Store
                        </label>
                        <div class="relative">
                            <BuildingStorefrontIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <select
                                v-model="storeId"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Stores</option>
                                <option v-for="store in stores" :key="store.id" :value="store.id">
                                    {{ store.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Product Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Product
                        </label>
                        <div class="relative">
                            <CubeIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <select
                                v-model="productId"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Products</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} ({{ product.sku }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Movement Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Movement Type
                        </label>
                        <div class="relative">
                            <FunnelIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <select
                                v-model="movementType"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">All Types</option>
                                <option v-for="type in movement_types" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Per Page -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Per Page
                        </label>
                        <select
                            v-model="perPage"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-2 mt-4">
                    <button
                        @click="applyFilters"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700"
                    >
                        Apply Filters
                    </button>
                    <button
                        @click="resetFilters"
                        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center space-x-4">
                    <div class="flex-1 relative">
                        <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by reference, product, store, or user..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ filteredData.length }} of {{ report_data.pagination.total }} movements
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500 text-right">
                    Generated: {{ formatDateTime(generated_at) }}
                </div>
            </div>

            <!-- Movements Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date & Time
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Reference
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantity
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    From Store
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    To Store
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created By
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="movement in filteredData" :key="movement.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDateTime(movement.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ movement.reference }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        <span :class="['p-1 rounded-lg', getMovementTypeColor(movement.type)]">
                                            <component :is="getMovementTypeIcon(movement.type)" class="w-4 h-4" />
                                        </span>
                                        <span :class="['px-2 py-1 text-xs rounded-full', getMovementTypeColor(movement.type)]">
                                            {{ getMovementTypeLabel(movement.type) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ movement.product?.name || 'N/A' }} 
                                    <span v-if="movement.product?.sku" class="text-xs text-gray-500">
                                        ({{ movement.product.sku }})
                                    </span>  
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="getQuantityColorClass(movement.quantity)">
                                    {{ movement.quantity > 0 ? '+' : '' }}{{ formatNumber(movement.quantity) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ movement.from_store?.name || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ movement.to_store?.name || 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex items-center space-x-1">
                                        <UserIcon class="w-4 h-4 text-gray-400" />
                                        <span>{{ movement.created_by }}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredData.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <ArrowPathIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No movements found</h3>
                                    <p class="text-gray-500">Try adjusting your filters or date range</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="report_data.pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing page {{ report_data.pagination.current_page }} of {{ report_data.pagination.last_page }}
                        ({{ report_data.pagination.total }} total movements)
                    </div>
                    <div class="flex space-x-2">
                        <button
                            @click="goToPage(1)"
                            :disabled="report_data.pagination.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            First
                        </button>
                        <button
                            @click="goToPage(report_data.pagination.current_page - 1)"
                            :disabled="report_data.pagination.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Previous
                        </button>
                        <span class="px-3 py-1 text-sm bg-indigo-50 text-indigo-600 border border-indigo-200 rounded-md">
                            {{ report_data.pagination.current_page }}
                        </span>
                        <button
                            @click="goToPage(report_data.pagination.current_page + 1)"
                            :disabled="report_data.pagination.current_page === report_data.pagination.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Next
                        </button>
                        <button
                            @click="goToPage(report_data.pagination.last_page)"
                            :disabled="report_data.pagination.current_page === report_data.pagination.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Last
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>