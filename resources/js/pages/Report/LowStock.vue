<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    ExclamationTriangleIcon,
    DocumentArrowDownIcon,
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    FunnelIcon,
    BuildingStorefrontIcon,
    CubeIcon,
    CurrencyDollarIcon,
    ArrowPathIcon,
    BellAlertIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    filters: {
        store_id?: string
        threshold: number
        per_page: number
        page: number
    }
    stores: Array<{
        id: number
        name: string
        code: string
    }>
    report_data: {
        data: Array<{
            store_id: number
            store_name: string
            branch_id: number
            product_id: number
            sku: string
            product_name: string
            quantity: number
            reserved_quantity: number
            available_quantity: number
            reorder_point: number
            cost_price: number
            selling_price: number
            value_at_risk: number
        }>
        pagination: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
        total_value_at_risk: number
    }
    generated_at: string
}>()

// Local filter state
const storeId = ref(props.filters.store_id || '')
const threshold = ref(props.filters.threshold)
const perPage = ref(props.filters.per_page)
const currentPage = ref(props.report_data.pagination.current_page)

// Search and filter state
const searchQuery = ref('')
const showFilters = ref(true)
const severityFilter = ref<'all' | 'critical' | 'warning' | 'low'>('all')

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
const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Get stock status and severity
const getStockStatus = (item: any) => {
    const available = item.available_quantity
    
    if (available <= 0) return { 
        label: 'Out of Stock', 
        severity: 'critical',
        class: 'bg-red-100 text-red-800',
        icon: ExclamationTriangleIcon,
        color: 'text-red-600'
    }
    if (available <= item.reorder_point / 2) return { 
        label: 'Critical Low', 
        severity: 'critical',
        class: 'bg-orange-100 text-orange-800',
        icon: ExclamationTriangleIcon,
        color: 'text-orange-600'
    }
    if (available <= item.reorder_point) return { 
        label: 'Low Stock', 
        severity: 'warning',
        class: 'bg-yellow-100 text-yellow-800',
        icon: BellAlertIcon,
        color: 'text-yellow-600'
    }
    return { 
        label: 'Normal', 
        severity: 'normal',
        class: 'bg-green-100 text-green-800',
        icon: CubeIcon,
        color: 'text-green-600'
    }
}

// Filtered and sorted data
const filteredData = computed(() => {
    let data = props.report_data.data

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        data = data.filter(item => 
            item.product_name.toLowerCase().includes(query) ||
            item.sku.toLowerCase().includes(query) ||
            item.store_name.toLowerCase().includes(query)
        )
    }

    // Apply severity filter
    if (severityFilter.value !== 'all') {
        data = data.filter(item => {
            const status = getStockStatus(item)
            return status.severity === severityFilter.value
        })
    }

    // Sort by available quantity (lowest first)
    return [...data].sort((a, b) => a.available_quantity - b.available_quantity)
})

// Summary statistics
const summaryStats = computed(() => {
    const criticalCount = props.report_data.data.filter(item => 
        item.available_quantity <= 0 || item.available_quantity <= item.reorder_point / 2
    ).length
    
    const lowCount = props.report_data.data.filter(item => 
        item.available_quantity > 0 && 
        item.available_quantity <= item.reorder_point &&
        item.available_quantity > item.reorder_point / 2
    ).length
    
    const normalCount = props.report_data.data.filter(item => 
        item.available_quantity > item.reorder_point
    ).length
    
    return {
        critical: criticalCount,
        low: lowCount,
        normal: normalCount,
        total: props.report_data.data.length
    }
})

// Apply filters
const applyFilters = () => {
    router.get('/reports/low-stock', {
        store_id: storeId.value || undefined,
        threshold: threshold.value,
        per_page: perPage.value
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

// Reset filters
const resetFilters = () => {
    storeId.value = ''
    threshold.value = 0
    perPage.value = 50
    searchQuery.value = ''
    severityFilter.value = 'all'
    applyFilters()
}

// Export report
const exportReport = () => {
    window.location.href = '/reports/export?type=low-stock&' + new URLSearchParams({
        store_id: storeId.value || '',
        threshold: threshold.value.toString()
    }).toString()
}

// Toggle filters
const toggleFilters = () => {
    showFilters.value = !showFilters.value
}

// Go to adjustment page for a product
const goToAdjustment = (productId: number) => {
    router.get(`/inventory/adjustment/create?store_id=${storeId.value || ''}&product_id=${productId}`)
}

// Get urgency color for progress bar
const getUrgencyColor = (available: number, reorderPoint: number) => {
    const ratio = available / reorderPoint
    if (ratio <= 0) return 'bg-red-500'
    if (ratio <= 0.3) return 'bg-orange-500'
    if (ratio <= 0.7) return 'bg-yellow-500'
    return 'bg-green-500'
}
</script>

<template>
    <Head title="Low Stock Report" />

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
                        <h1 class="text-2xl font-bold text-gray-900">Low Stock Report</h1>
                        <p class="text-gray-600">Monitor inventory levels and reorder points</p>
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

            <!-- Alert Banner -->
            <div v-if="summaryStats.critical > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <ExclamationTriangleIcon class="w-6 h-6 text-red-600 mr-3" />
                    <div>
                        <h3 class="text-lg font-medium text-red-800">Critical Stock Alert</h3>
                        <p class="text-red-700">
                            {{ summaryStats.critical }} item(s) are critically low or out of stock.
                            Immediate attention required.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(summaryStats.total) }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Critical Items</p>
                            <p class="text-2xl font-bold text-red-600">{{ formatNumber(summaryStats.critical) }}</p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <ExclamationTriangleIcon class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Low Stock Items</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ formatNumber(summaryStats.low) }}</p>
                        </div>
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <BellAlertIcon class="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Value at Risk</p>
                            <p class="text-2xl font-bold text-orange-600">{{ formatCurrency(report_data.total_value_at_risk) }}</p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <CurrencyDollarIcon class="w-6 h-6 text-orange-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div v-if="showFilters" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

                    <!-- Threshold Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Threshold
                        </label>
                        <input
                            v-model.number="threshold"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p class="text-xs text-gray-500 mt-1">Show items below reorder point + threshold</p>
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
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </div>

                    <!-- Severity Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Severity
                        </label>
                        <select
                            v-model="severityFilter"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="all">All Items</option>
                            <option value="critical">Critical Only</option>
                            <option value="warning">Warning Only</option>
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

            <!-- Search -->
            <div class="flex justify-between items-center">
                <div class="flex-1 max-w-md relative">
                    <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by product, SKU, or store..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
                <div class="text-sm text-gray-500">
                    {{ filteredData.length }} items shown
                </div>
            </div>

            <div class="text-xs text-gray-500 text-right mb-2">
                Generated: {{ formatDateTime(generated_at) }}
            </div>

            <!-- Low Stock Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Store
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
                                    Reorder Point
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Value at Risk
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in filteredData" :key="`${item.store_id}-${item.product_id}`" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ item.store_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ item.product_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ item.sku }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatNumber(item.quantity) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-orange-600">
                                    {{ formatNumber(item.reserved_quantity) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="getStockStatus(item).color">
                                    {{ formatNumber(item.available_quantity) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatNumber(item.reorder_point) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span :class="['px-2 py-1 text-xs rounded-full inline-flex items-center', getStockStatus(item).class]">
                                        <component :is="getStockStatus(item).icon" class="w-3 h-3 mr-1" />
                                        {{ getStockStatus(item).label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-orange-600">
                                    {{ formatCurrency(item.value_at_risk) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button
                                        @click="goToAdjustment(item.product_id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="Adjust Stock"
                                    >
                                        <ArrowPathIcon class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>

                            <!-- Progress bars for visual representation -->
                            <tr v-for="item in filteredData.slice(0, 5)" :key="`progress-${item.store_id}-${item.product_id}`" class="bg-gray-50">
                                <td colspan="10" class="px-6 py-2">
                                    <div class="flex items-center space-x-4">
                                        <span class="text-xs text-gray-600 w-32 truncate">{{ item.product_name }}</span>
                                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div 
                                                class="h-full rounded-full transition-all"
                                                :class="getUrgencyColor(item.available_quantity, item.reorder_point)"
                                                :style="{ width: Math.min(100, (item.available_quantity / item.reorder_point) * 100) + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-xs font-medium" :class="getStockStatus(item).color">
                                            {{ item.available_quantity }} / {{ item.reorder_point }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="report_data.pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing page {{ report_data.pagination.current_page }} of {{ report_data.pagination.last_page }}
                        ({{ report_data.pagination.total }} total items)
                    </div>
                    <div class="flex space-x-2">
                        <button
                            @click="router.get('/reports/low-stock', { ...filters, page: 1 })"
                            :disabled="report_data.pagination.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            First
                        </button>
                        <button
                            @click="router.get('/reports/low-stock', { ...filters, page: report_data.pagination.current_page - 1 })"
                            :disabled="report_data.pagination.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Previous
                        </button>
                        <span class="px-3 py-1 text-sm bg-indigo-50 text-indigo-600 border border-indigo-200 rounded-md">
                            {{ report_data.pagination.current_page }}
                        </span>
                        <button
                            @click="router.get('/reports/low-stock', { ...filters, page: report_data.pagination.current_page + 1 })"
                            :disabled="report_data.pagination.current_page === report_data.pagination.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Next
                        </button>
                        <button
                            @click="router.get('/reports/low-stock', { ...filters, page: report_data.pagination.last_page })"
                            :disabled="report_data.pagination.current_page === report_data.pagination.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Last
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredData.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
                <CubeIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                <h3 class="text-lg font-medium text-gray-900 mb-1">No low stock items found</h3>
                <p class="text-gray-500">All inventory levels are healthy or try adjusting your filters</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>