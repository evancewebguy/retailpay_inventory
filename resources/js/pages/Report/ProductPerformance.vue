<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    CubeIcon,
    DocumentArrowDownIcon,
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    FunnelIcon,
    CalendarIcon,
    BuildingStorefrontIcon,
    CurrencyDollarIcon,
    ChartBarIcon,
    TableCellsIcon,
    ArrowPathIcon,
    StarIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    filters: {
        from_date: string
        to_date: string
        store_id?: string
        category?: string
        limit: number
    }
    stores: Array<{
        id: number
        name: string
        code: string
    }>
    categories?: Array<string>
    report_data: {
        data: Array<{
            id: number
            sku: string
            product_name: string
            total_quantity_sold: number
            number_of_sales: number
            total_revenue: number
            total_cost: number
            total_profit: number
            profit_margin: number
            average_quantity_per_sale: number
        }>
        totals: {
            total_revenue: number
            total_profit: number
            total_quantity: number
            average_margin: number
        }
    }
    generated_at: string
}>()

// Local filter state
const fromDate = ref(props.filters.from_date)
const toDate = ref(props.filters.to_date)
const storeId = ref(props.filters.store_id || '')
const category = ref(props.filters.category || '')
const limit = ref(props.filters.limit)

// View mode
const viewMode = ref<'table' | 'chart'>('table')
const showFilters = ref(true)
const sortBy = ref<'quantity' | 'revenue' | 'profit' | 'margin'>('quantity')
const sortDirection = ref<'asc' | 'desc'>('desc')

// Search state
const searchQuery = ref('')

// Filtered and sorted data
const filteredData = computed(() => {
    let data = props.report_data.data

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase()
        data = data.filter(item => 
            item.product_name.toLowerCase().includes(query) ||
            item.sku.toLowerCase().includes(query)
        )
    }

    // Apply sorting
    return [...data].sort((a, b) => {
        let comparison = 0
        switch (sortBy.value) {
            case 'quantity':
                comparison = a.total_quantity_sold - b.total_quantity_sold
                break
            case 'revenue':
                comparison = a.total_revenue - b.total_revenue
                break
            case 'profit':
                comparison = a.total_profit - b.total_profit
                break
            case 'margin':
                comparison = a.profit_margin - b.profit_margin
                break
        }
        return sortDirection.value === 'desc' ? -comparison : comparison
    })
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
    return value.toFixed(1) + '%'
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

// Get performance color class
const getPerformanceColor = (value: number, type: 'margin' | 'quantity') => {
    if (type === 'margin') {
        if (value >= 40) return 'text-green-600'
        if (value >= 20) return 'text-blue-600'
        if (value >= 10) return 'text-yellow-600'
        return 'text-red-600'
    } else {
        // For quantity - find relative performance
        const max = Math.max(...props.report_data.data.map(d => d.total_quantity_sold))
        const ratio = value / max
        if (ratio >= 0.7) return 'text-green-600'
        if (ratio >= 0.4) return 'text-blue-600'
        if (ratio >= 0.1) return 'text-yellow-600'
        return 'text-gray-600'
    }
}

// Get progress bar width for visualization
const getProgressWidth = (value: number, max: number) => {
    return (value / max) * 100 + '%'
}

// Calculate max values for visualization
const maxQuantity = computed(() => 
    Math.max(...props.report_data.data.map(d => d.total_quantity_sold), 1)
)
const maxRevenue = computed(() => 
    Math.max(...props.report_data.data.map(d => d.total_revenue), 1)
)
const maxProfit = computed(() => 
    Math.max(...props.report_data.data.map(d => d.total_profit), 1)
)

// Apply filters
const applyFilters = () => {
    router.get('/reports/products', {
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || undefined,
        category: category.value || undefined,
        limit: limit.value
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
    category.value = ''
    limit.value = 20
    searchQuery.value = ''
    applyFilters()
}

// Export report
const exportReport = () => {
    window.location.href = '/reports/export?type=products&' + new URLSearchParams({
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || '',
        category: category.value || ''
    }).toString()
}

// Toggle filters
const toggleFilters = () => {
    showFilters.value = !showFilters.value
}

// Toggle sort
const toggleSort = (field: 'quantity' | 'revenue' | 'profit' | 'margin') => {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc'
    } else {
        sortBy.value = field
        sortDirection.value = 'desc'
    }
}

// Get sort indicator
const getSortIndicator = (field: string) => {
    if (sortBy.value !== field) return ''
    return sortDirection.value === 'desc' ? ' ↓' : ' ↑'
}
</script>

<template>
    <Head title="Product Performance Report" />

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
                        <h1 class="text-2xl font-bold text-gray-900">Product Performance Report</h1>
                        <p class="text-gray-600">Analyze best-selling products and profitability</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Products Analyzed</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.data.length) }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <CubeIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Units Sold</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.totals.total_quantity) }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <TrendingUpIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600">{{ formatCurrency(report_data.totals.total_revenue) }}</p>
                        </div>
                        <div class="p-3 bg-indigo-100 rounded-full">
                            <CurrencyDollarIcon class="w-6 h-6 text-indigo-600" />
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Profit</p>
                            <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(report_data.totals.total_profit) }}</p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <StarIcon class="w-6 h-6 text-purple-600" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Average Margin Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Average Profit Margin</p>
                        <p class="text-3xl font-bold" :class="getPerformanceColor(report_data.totals.average_margin, 'margin')">
                            {{ formatPercentage(report_data.totals.average_margin) }}
                        </p>
                    </div>
                    <div class="flex-1 max-w-2xl ml-8">
                        <div class="h-4 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-green-500': report_data.totals.average_margin >= 40,
                                    'bg-blue-500': report_data.totals.average_margin >= 20 && report_data.totals.average_margin < 40,
                                    'bg-yellow-500': report_data.totals.average_margin >= 10 && report_data.totals.average_margin < 20,
                                    'bg-red-500': report_data.totals.average_margin < 10
                                }"
                                :style="{ width: report_data.totals.average_margin + '%' }"
                            ></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>0%</span>
                            <span>10%</span>
                            <span>20%</span>
                            <span>40%</span>
                            <span>60%+</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div v-if="showFilters" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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

                    <!-- Category Filter (if categories exist) -->
                    <div v-if="categories && categories.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Category
                        </label>
                        <select
                            v-model="category"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Categories</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                    </div>

                    <!-- Limit Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Products Shown
                        </label>
                        <select
                            v-model="limit"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="10">Top 10</option>
                            <option value="20">Top 20</option>
                            <option value="50">Top 50</option>
                            <option value="100">Top 100</option>
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

            <!-- Search and View Toggle -->
            <div class="flex justify-between items-center">
                <div class="flex-1 max-w-md relative">
                    <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search products..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
                <div class="flex space-x-2">
                    <button
                        @click="viewMode = 'table'"
                        class="px-3 py-1 rounded-lg text-sm flex items-center"
                        :class="viewMode === 'table' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        <TableCellsIcon class="w-4 h-4 mr-1" />
                        Table
                    </button>
                    <button
                        @click="viewMode = 'chart'"
                        class="px-3 py-1 rounded-lg text-sm flex items-center"
                        :class="viewMode === 'chart' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        <ChartBarIcon class="w-4 h-4 mr-1" />
                        Chart
                    </button>
                </div>
            </div>

            <div class="text-xs text-gray-500 text-right mb-2">
                Generated: {{ formatDateTime(generated_at) }}
            </div>

            <!-- Chart View -->
            <div v-if="viewMode === 'chart'" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Product Performance Visualization</h2>
                
                <div class="space-y-4">
                    <div v-for="item in filteredData.slice(0, 10)" :key="item.id" class="space-y-2">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-medium text-gray-900">{{ item.product_name }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ item.sku }})</span>
                            </div>
                            <div class="text-sm font-medium" :class="getPerformanceColor(item.profit_margin, 'margin')">
                                {{ formatPercentage(item.profit_margin) }} margin
                            </div>
                        </div>
                        
                        <!-- Quantity bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Quantity Sold</span>
                                <span class="font-medium">{{ formatNumber(item.total_quantity_sold) }} units</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-blue-500 rounded-full"
                                    :style="{ width: getProgressWidth(item.total_quantity_sold, maxQuantity) }"
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Revenue bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Revenue</span>
                                <span class="font-medium text-green-600">{{ formatCurrency(item.total_revenue) }}</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-green-500 rounded-full"
                                    :style="{ width: getProgressWidth(item.total_revenue, maxRevenue) }"
                                ></div>
                            </div>
                        </div>
                        
                        <!-- Profit bar -->
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Profit</span>
                                <span class="font-medium text-indigo-600">{{ formatCurrency(item.total_profit) }}</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-indigo-500 rounded-full"
                                    :style="{ width: getProgressWidth(item.total_profit, maxProfit) }"
                                ></div>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-100 pt-2"></div>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div v-else class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                    @click="toggleSort('quantity')">
                                    Product {{ getSortIndicator('quantity') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    SKU
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                    @click="toggleSort('quantity')">
                                    Quantity Sold {{ getSortIndicator('quantity') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    # of Sales
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                    @click="toggleSort('revenue')">
                                    Revenue {{ getSortIndicator('revenue') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                    @click="toggleSort('profit')">
                                    Profit {{ getSortIndicator('profit') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                    @click="toggleSort('margin')">
                                    Margin {{ getSortIndicator('margin') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Avg Qty/Sale
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in filteredData" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ item.product_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ item.sku }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="getPerformanceColor(item.total_quantity_sold, 'quantity')">
                                    {{ formatNumber(item.total_quantity_sold) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatNumber(item.number_of_sales) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                    {{ formatCurrency(item.total_revenue) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-indigo-600">
                                    {{ formatCurrency(item.total_profit) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                    :class="getPerformanceColor(item.profit_margin, 'margin')">
                                    {{ formatPercentage(item.profit_margin) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ Number(item.average_quantity_per_sale || 0).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    TOTALS ({{ filteredData.length }} products)
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                    {{ formatNumber(report_data.totals.total_quantity) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
                                    {{ formatCurrency(report_data.totals.total_revenue) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-indigo-600">
                                    {{ formatCurrency(report_data.totals.total_profit) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="getPerformanceColor(report_data.totals.average_margin, 'margin')">
                                    {{ formatPercentage(report_data.totals.average_margin) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredData.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
                <CubeIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                <h3 class="text-lg font-medium text-gray-900 mb-1">No product data found</h3>
                <p class="text-gray-500">Try adjusting your filters or date range</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>