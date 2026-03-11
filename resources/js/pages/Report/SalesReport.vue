<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    ShoppingCartIcon,
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
    ArrowPathIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    filters: {
        from_date: string
        to_date: string
        store_id?: string
        interval: 'daily' | 'weekly' | 'monthly'
    }
    stores: Array<{
        id: number
        name: string
        code: string
    }>
    report_data: {
        interval: 'daily' | 'weekly' | 'monthly'
        data: Array<{
            period: string
            total_sales: number
            total_items_sold: number
            total_revenue: number
            total_discount: number
            total_profit: number
        }>
        totals: {
            total_sales: number
            total_items_sold: number
            total_revenue: number
            total_discount: number
            total_profit: number
            average_sale_value: number
            average_items_per_sale: number
        }
    }
    generated_at: string
}>()

// Local filter state
const fromDate = ref(props.filters.from_date)
const toDate = ref(props.filters.to_date)
const storeId = ref(props.filters.store_id || '')
const interval = ref<'daily' | 'weekly' | 'monthly'>(props.filters.interval)

// View mode
const viewMode = ref<'table' | 'chart'>('table')
const showFilters = ref(true)

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

// Format date based on interval
const formatPeriod = (period: string) => {
    if (interval.value === 'daily') {
        return new Date(period).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        })
    } else if (interval.value === 'weekly') {
        const [year, week] = period.split('-')
        return `Week ${week}, ${year}`
    } else {
        const [year, month] = period.split('-')
        return new Date(parseInt(year), parseInt(month) - 1).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long'
        })
    }
}

// Format date for display
const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Calculate chart data for visualization
const chartData = computed(() => {
    const labels: string[] = []
    const revenueData: number[] = []
    const profitData: number[] = []
    const salesData: number[] = []

    props.report_data.data.forEach(item => {
        labels.push(formatPeriod(item.period))
        revenueData.push(item.total_revenue)
        profitData.push(item.total_profit)
        salesData.push(item.total_sales)
    })

    // Find max value for scaling
    const maxValue = Math.max(...revenueData, 100)

    return {
        labels,
        revenueData,
        profitData,
        salesData,
        maxValue
    }
})

// Apply filters
const applyFilters = () => {
    router.get('/reports/sales', {
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || undefined,
        interval: interval.value
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
    interval.value = 'daily'
    applyFilters()
}

// Export report
const exportReport = () => {
    window.location.href = '/reports/export?type=sales&' + new URLSearchParams({
        from_date: fromDate.value,
        to_date: toDate.value,
        store_id: storeId.value || '',
        interval: interval.value
    }).toString()
}

// Toggle filters
const toggleFilters = () => {
    showFilters.value = !showFilters.value
}

// Get percentage change (mock function - in real app would compare with previous period)
const getTrend = (value: number) => {
    // This is a placeholder - in a real app, you'd compare with previous period
    const random = Math.random()
    if (random > 0.6) return { direction: 'up', percentage: Math.round(random * 20) }
    if (random > 0.3) return { direction: 'down', percentage: Math.round(random * 15) }
    return { direction: 'stable', percentage: 0 }
}

const revenueTrend = computed(() => getTrend(props.report_data.totals.total_revenue))
const profitTrend = computed(() => getTrend(props.report_data.totals.total_profit))
const salesTrend = computed(() => getTrend(props.report_data.totals.total_sales))
</script>

<template>
    <Head title="Sales Report" />

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
                        <h1 class="text-2xl font-bold text-gray-900">Sales Report</h1>
                        <p class="text-gray-600">Analyze sales performance across stores</p>
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
                <!-- Total Sales -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Total Sales</p>
                        <span class="text-xs font-medium px-2 py-1 rounded-full"
                              :class="{
                                  'bg-green-100 text-green-800': salesTrend.direction === 'up',
                                  'bg-red-100 text-red-800': salesTrend.direction === 'down',
                                  'bg-gray-100 text-gray-800': salesTrend.direction === 'stable'
                              }">
                            {{ salesTrend.direction === 'up' ? '↑' : salesTrend.direction === 'down' ? '↓' : '→' }}
                            {{ salesTrend.percentage }}%
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.totals.total_sales) }}</p>
                </div>

                <!-- Items Sold -->
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-600 mb-2">Items Sold</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatNumber(report_data.totals.total_items_sold) }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        Avg {{ formatNumber(report_data.totals.average_items_per_sale) }} per sale
                    </p>
                </div>

                <!-- Total Revenue -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Total Revenue</p>
                        <span class="text-xs font-medium px-2 py-1 rounded-full"
                              :class="{
                                  'bg-green-100 text-green-800': revenueTrend.direction === 'up',
                                  'bg-red-100 text-red-800': revenueTrend.direction === 'down',
                                  'bg-gray-100 text-gray-800': revenueTrend.direction === 'stable'
                              }">
                            {{ revenueTrend.direction === 'up' ? '↑' : revenueTrend.direction === 'down' ? '↓' : '→' }}
                            {{ revenueTrend.percentage }}%
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-green-600">{{ formatCurrency(report_data.totals.total_revenue) }}</p>
                </div>

                <!-- Total Profit -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm text-gray-600">Total Profit</p>
                        <span class="text-xs font-medium px-2 py-1 rounded-full"
                              :class="{
                                  'bg-green-100 text-green-800': profitTrend.direction === 'up',
                                  'bg-red-100 text-red-800': profitTrend.direction === 'down',
                                  'bg-gray-100 text-gray-800': profitTrend.direction === 'stable'
                              }">
                            {{ profitTrend.direction === 'up' ? '↑' : profitTrend.direction === 'down' ? '↓' : '→' }}
                            {{ profitTrend.percentage }}%
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-indigo-600">{{ formatCurrency(report_data.totals.total_profit) }}</p>
                </div>
            </div>

            <!-- Average Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-600 mb-2">Average Sale Value</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(report_data.totals.average_sale_value) }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-sm text-gray-600 mb-2">Total Discounts Given</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(report_data.totals.total_discount) }}</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div v-if="showFilters" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

                    <!-- Interval Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Interval
                        </label>
                        <select
                            v-model="interval"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
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

            <!-- View Toggle -->
            <div class="flex justify-between items-center">
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
                        @click="viewMode = 'chart'"
                        class="px-3 py-1 rounded-lg text-sm flex items-center"
                        :class="viewMode === 'chart' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        <ChartBarIcon class="w-4 h-4 mr-1" />
                        Chart View
                    </button>
                </div>
                <div class="text-xs text-gray-500">
                    Generated: {{ formatDateTime(generated_at) }}
                </div>
            </div>

            <!-- Chart View -->
            <div v-if="viewMode === 'chart'" class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Sales Trend</h2>
                
                <!-- Simple bar chart visualization -->
                <div class="h-80 relative">
                    <div class="absolute inset-0 flex items-end justify-around">
                        <div v-for="(revenue, index) in chartData.revenueData" :key="index" 
                             class="flex flex-col items-center w-16">
                            <div class="relative group w-full">
                                <!-- Revenue bar -->
                                <div class="bg-indigo-500 rounded-t w-full transition-all duration-300"
                                     :style="{ height: `${(revenue / chartData.maxValue) * 200}px` }">
                                    <div class="absolute -top-8 left-1/2 transform -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap z-10">
                                        Revenue: {{ formatCurrency(revenue) }}
                                    </div>
                                </div>
                                <!-- Profit bar (overlay) -->
                                <div class="bg-green-500 rounded-t w-full absolute bottom-0 transition-all duration-300 opacity-75"
                                     :style="{ height: `${(chartData.profitData[index] / chartData.maxValue) * 200}px` }">
                                    <div class="absolute -top-8 left-1/2 transform -translate-x-1/2 hidden group-hover:block bg-gray-900 text-white text-xs rounded py-1 px-2 whitespace-nowrap z-10">
                                        Profit: {{ formatCurrency(chartData.profitData[index]) }}
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs text-gray-500 mt-2 truncate max-w-full">
                                {{ chartData.labels[index] }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="flex justify-center space-x-6 mt-4">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-indigo-500 rounded mr-2"></div>
                        <span class="text-sm text-gray-600">Revenue</span>
                    </div>
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded mr-2"></div>
                        <span class="text-sm text-gray-600">Profit</span>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div v-else class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Sales Data</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Period
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sales Count
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Items Sold
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Revenue
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Discounts
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Profit
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Margin
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in report_data.data" :key="item.period" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ formatPeriod(item.period) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatNumber(item.total_sales) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ formatNumber(item.total_items_sold) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                    {{ formatCurrency(item.total_revenue) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-orange-600">
                                    {{ formatCurrency(item.total_discount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-indigo-600">
                                    {{ formatCurrency(item.total_profit) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    {{ item.total_revenue > 0 ? ((item.total_profit / item.total_revenue) * 100).toFixed(1) : 0 }}%
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    TOTALS
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                    {{ formatNumber(report_data.totals.total_sales) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                    {{ formatNumber(report_data.totals.total_items_sold) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
                                    {{ formatCurrency(report_data.totals.total_revenue) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-orange-600">
                                    {{ formatCurrency(report_data.totals.total_discount) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-indigo-600">
                                    {{ formatCurrency(report_data.totals.total_profit) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                    {{ report_data.totals.total_revenue > 0 ? ((report_data.totals.total_profit / report_data.totals.total_revenue) * 100).toFixed(1) : 0 }}%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="report_data.data.length === 0" class="bg-white rounded-lg shadow p-12 text-center">
                <ShoppingCartIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                <h3 class="text-lg font-medium text-gray-900 mb-1">No sales data found</h3>
                <p class="text-gray-500">Try adjusting your filters or date range</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>