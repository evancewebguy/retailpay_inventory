<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    CubeIcon,
    DocumentArrowDownIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    BuildingStorefrontIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    TableCellsIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    filters: {
        branch_id?: string
        store_id?: string
        per_page?: number
    }
    branches: Array<{
        id: number
        name: string
    }>
    stores: Array<{
        id: number
        name: string
        code: string
        branch_id: number
        branch?: {
            id: number
            name: string
        }
    }>
    report_data: {
        data: Array<{
            branch_id: number
            branch_name: string
            store_id: number
            store_name: string
            product_id: number
            sku: string
            product_name: string
            quantity: number
            reserved_quantity: number
            available_quantity: number
            cost_price: number
            selling_price: number
            total_cost: number
            total_value: number
            available_value: number
        }>
        totals: {
            total_cost: number
            total_value: number
            available_value: number
            potential_profit: number
            total_items: number
            total_quantity: number
        }
        by_branch: Record<string, {
            total_value: number
            total_cost: number
            item_count: number
        }>
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
const branchId = ref(props.filters.branch_id || '')
const storeId = ref(props.filters.store_id || '')
const perPage = ref(props.filters.per_page || 20)
const currentPage = ref(props.report_data.pagination.current_page)

// Search state
const searchQuery = ref('')
const viewMode = ref<'table' | 'summary'>('table') // 'table' or 'summary'

// Filtered data based on search
const filteredData = computed(() => {
    if (!searchQuery.value) return props.report_data.data
    
    const query = searchQuery.value.toLowerCase()
    return props.report_data.data.filter(item => 
        item.product_name.toLowerCase().includes(query) ||
        item.sku.toLowerCase().includes(query)
    )
})

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

// Format percentage
const formatPercentage = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'percent',
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }).format(value / 100)
}

// Get stores filtered by branch
const filteredStores = computed(() => {
    if (!branchId.value) return props.stores
    return props.stores.filter(store => store.branch_id === Number(branchId.value))
})

// Apply filters
const applyFilters = () => {
    router.get('/reports/stock-valuation', {
        branch_id: branchId.value || undefined,
        store_id: storeId.value || undefined,
        per_page: perPage.value
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

// Reset filters
const resetFilters = () => {
    branchId.value = ''
    storeId.value = ''
    perPage.value = 20
    searchQuery.value = ''
    applyFilters()
}

// Export report
const exportReport = () => {
    window.location.href = '/reports/export?type=valuation&' + new URLSearchParams({
        branch_id: branchId.value || '',
        store_id: storeId.value || ''
    }).toString()
}

// Go to page
const goToPage = (page: number) => {
    if (page < 1 || page > props.report_data.pagination.last_page) return
    
    router.get('/reports/stock-valuation', {
        ...props.filters,
        page
    }, {
        preserveState: true,
        preserveScroll: true
    })
}

// Calculate margin percentage
const calculateMargin = (sellingPrice: number, costPrice: number) => {
    if (sellingPrice === 0) return 0
    return ((sellingPrice - costPrice) / sellingPrice) * 100
}

// Get margin color class
const getMarginColorClass = (margin: number) => {
    if (margin >= 40) return 'text-green-600'
    if (margin >= 20) return 'text-blue-600'
    if (margin >= 10) return 'text-yellow-600'
    return 'text-red-600'
}
</script>

<template>
    <Head title="Stock Valuation Report" />

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
                        <h1 class="text-2xl font-bold text-gray-900">Stock Valuation Report</h1>
                        <p class="text-gray-600">Comprehensive inventory valuation by store</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="exportReport"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5 mr-2" />
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Branch Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Branch
                        </label>
                        <select
                            v-model="branchId"
                            @change="storeId = ''"
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
                            <option v-for="store in filteredStores" :key="store.id" :value="store.id">
                                {{ store.name }}
                            </option>
                        </select>
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
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Search
                        </label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search products..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between mt-4">
                    <div class="flex space-x-2">
                        <button
                            @click="viewMode = 'table'"
                            class="px-3 py-1 rounded-lg text-sm flex items-center"
                            :class="viewMode === 'table' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        >
                            <TableCellsIcon class="w-4 h-4 mr-1" />
                            Table View
                        </button>
                        <button
                            @click="viewMode = 'summary'"
                            class="px-3 py-1 rounded-lg text-sm flex items-center"
                            :class="viewMode === 'summary' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        >
                            <ChartBarIcon class="w-4 h-4 mr-1" />
                            Summary View
                        </button>
                    </div>
                    <div class="flex space-x-2">
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

                <div class="mt-2 text-xs text-gray-500 text-right">
                    Generated: {{ new Date(generated_at).toLocaleString() }}
                </div>
            </div>

            <!-- Totals Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600">Total Items</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.totals.total_items) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600">Total Quantity</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.totals.total_quantity) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600">Total Cost</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(report_data.totals.total_cost) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600">Total Value</p>
                    <p class="text-2xl font-bold text-green-600">{{ formatCurrency(report_data.totals.total_value) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-600">Potential Profit</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(report_data.totals.potential_profit) }}</p>
                </div>
            </div>

            <!-- Summary View -->
            <div v-if="viewMode === 'summary'" class="space-y-6">
                <!-- By Branch -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Valuation by Branch</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Items</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Value</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Potential Profit</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(data, branchName) in report_data.by_branch" :key="branchName">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ branchName }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        {{ formatNumber(data.item_count) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        {{ formatCurrency(data.total_cost) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                        {{ formatCurrency(data.total_value) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-indigo-600">
                                        {{ formatCurrency(data.total_value - data.total_cost) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <span :class="getMarginColorClass(((data.total_value - data.total_cost) / data.total_value) * 100)">
                                            {{ formatPercentage(((data.total_value - data.total_cost) / data.total_value) * 100) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div v-else class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Inventory Items</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Store</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">On Hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reserved</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Available</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selling Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Value</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in filteredData" :key="`${item.store_id}-${item.product_id}`" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ item.branch_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ item.store_name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ item.product_name }}</div>
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
                                    :class="{
                                        'text-green-600': item.available_quantity > 10,
                                        'text-yellow-600': item.available_quantity > 0 && item.available_quantity <= 10,
                                        'text-red-600': item.available_quantity <= 0
                                    }">
                                    {{ formatNumber(item.available_quantity) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatCurrency(item.cost_price) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatCurrency(item.selling_price) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatCurrency(item.total_cost) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                    {{ formatCurrency(item.total_value) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span :class="getMarginColorClass(calculateMargin(item.selling_price, item.cost_price))">
                                        {{ formatPercentage(calculateMargin(item.selling_price, item.cost_price)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="filteredData.length === 0">
                                <td colspan="12" class="px-6 py-12 text-center">
                                    <CubeIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No inventory items found</h3>
                                    <p class="text-gray-500">Try adjusting your filters</p>
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