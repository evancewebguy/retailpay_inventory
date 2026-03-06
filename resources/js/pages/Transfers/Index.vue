<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, watch, computed } from 'vue'
import { useToast } from 'vue-toastification'
import {
    ArrowPathIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    EyeIcon,
    CheckCircleIcon,
    XCircleIcon,
    TruckIcon,
    ClockIcon,
    DocumentArrowDownIcon,
    FunnelIcon
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'

const toast = useToast()

// Define props from the controller
const props = defineProps<{
    transfers: {
        data: Array<{
            id: number
            transfer_number: string
            from_store_id: number
            to_store_id: number
            status: 'PENDING' | 'PROCESSING' | 'SHIPPED' | 'RECEIVED' | 'CANCELLED'
            expected_delivery_date: string | null
            notes: string | null
            created_at: string
            from_store: {
                id: number
                name: string
                code: string
                branch?: {
                    name: string
                }
            }
            to_store: {
                id: number
                name: string
                code: string
                branch?: {
                    name: string
                }
            }
            items: Array<{
                id: number
                product_id: number
                quantity_requested: number
                quantity_shipped: number | null
                quantity_received: number | null
                product: {
                    id: number
                    name: string
                    sku: string
                }
            }>
            creator: {
                id: number
                name: string
            }
            approver?: {
                id: number
                name: string
            } | null
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
    filters: {
        status?: string
        from_store_id?: string
        to_store_id?: string
        search?: string
        per_page?: number
    }
}>()

// Local state for filters
const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const fromStoreId = ref(props.filters.from_store_id || '')
const toStoreId = ref(props.filters.to_store_id || '')
const perPage = ref(props.filters.per_page || 20)

// Status options for filter
const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'PENDING', label: 'Pending', color: 'yellow' },
    { value: 'PROCESSING', label: 'Processing', color: 'blue' },
    { value: 'SHIPPED', label: 'Shipped', color: 'purple' },
    { value: 'RECEIVED', label: 'Received', color: 'green' },
    { value: 'CANCELLED', label: 'Cancelled', color: 'red' },
]

// Watch for filter changes
watch([search, status, fromStoreId, toStoreId, perPage], () => {
    handleFilterChange()
})

// Debounce search input
let searchTimeout: ReturnType<typeof setTimeout>
const handleFilterChange = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/transfers', {
            search: search.value,
            status: status.value,
            from_store_id: fromStoreId.value,
            to_store_id: toStoreId.value,
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
    status.value = ''
    fromStoreId.value = ''
    toStoreId.value = ''
    perPage.value = 20
}

// Get status badge class
const getStatusBadgeClass = (status: string) => {
    const classes = {
        PENDING: 'bg-yellow-100 text-yellow-800',
        PROCESSING: 'bg-blue-100 text-blue-800',
        SHIPPED: 'bg-purple-100 text-purple-800',
        RECEIVED: 'bg-green-100 text-green-800',
        CANCELLED: 'bg-red-100 text-red-800'
    }
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

// Get status icon
const getStatusIcon = (status: string) => {
    const icons = {
        PENDING: ClockIcon,
        PROCESSING: ArrowPathIcon,
        SHIPPED: TruckIcon,
        RECEIVED: CheckCircleIcon,
        CANCELLED: XCircleIcon
    }
    return icons[status as keyof typeof icons] || ClockIcon
}

// Format date
const formatDate = (date: string | null) => {
    if (!date) return 'N/A'
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

// Calculate progress percentage
const calculateProgress = (transfer: any) => {
    if (transfer.status === 'RECEIVED') return 100
    if (transfer.status === 'CANCELLED') return 0
    
    const totalItems = transfer.items.length
    if (totalItems === 0) return 0
    
    const shippedItems = transfer.items.filter((item: any) => item.quantity_shipped).length
    const receivedItems = transfer.items.filter((item: any) => item.quantity_received).length
    
    if (transfer.status === 'SHIPPED') return Math.round((shippedItems / totalItems) * 100)
    if (transfer.status === 'PROCESSING') return Math.round((shippedItems / totalItems) * 50)
    
    return 0
}

// Check if user can approve
const canApprove = (transfer: any) => {
    // This would need actual permission check from backend
    // For now, just check if status is PENDING
    return transfer.status === 'PENDING'
}

// Check if user can receive
const canReceive = (transfer: any) => {
    return transfer.status === 'SHIPPED'
}

// Actions
const viewTransfer = (id: number) => {
    router.get(`/transfers/${id}`)
}

const approveTransfer = (id: number) => {
    router.post(`/transfers/${id}/approve`, {}, {
        onSuccess: () => {
            toast.success('Transfer approved successfully')
        },
        onError: () => {
            toast.error('Failed to approve transfer')
        }
    })
}

const receiveTransfer = (id: number) => {
    router.get(`/transfers/${id}/receive`)
}

const createTransfer = () => {
    router.get('/transfers/create')
}

const exportTransfers = () => {
    window.location.href = '/transfers/export?' + new URLSearchParams({
        status: status.value,
        from_store_id: fromStoreId.value,
        to_store_id: toStoreId.value,
        search: search.value,
    }).toString()
}

// Pagination
const goToPage = (page: number) => {
    router.get('/transfers', {
        ...props.filters,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

// Count active filters
const activeFiltersCount = computed(() => {
    let count = 0
    if (search.value) count++
    if (status.value) count++
    if (fromStoreId.value) count++
    if (toStoreId.value) count++
    return count
})
</script>

<template>
    <Head title="Transfers" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Stock Transfers</h1>
                    <p class="text-gray-600">Manage inventory transfers between stores</p>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="exportTransfers"
                        class="bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 flex items-center"
                    >
                        <DocumentArrowDownIcon class="w-5 h-5 mr-2" />
                        Export
                    </button>
                    <button
                        @click="createTransfer"
                        class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center"
                    >
                        <PlusIcon class="w-5 h-5 mr-2" />
                        New Transfer
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
                                placeholder="Search by transfer number..."
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
                            v-model="status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <!-- From Store Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            From Store
                        </label>
                        <select
                            v-model="fromStoreId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Stores</option>
                            <option v-for="store in stores" :key="store.id" :value="store.id">
                                {{ store.name }}
                            </option>
                        </select>
                    </div>

                    <!-- To Store Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            To Store
                        </label>
                        <select
                            v-model="toStoreId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">All Stores</option>
                            <option v-for="store in stores" :key="store.id" :value="store.id">
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
                </div>

                <!-- Active Filters -->
                <div v-if="activeFiltersCount > 0" class="mt-4 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Active filters:</span>
                        <span v-if="search" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            Search: {{ search }}
                        </span>
                        <span v-if="status" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            Status: {{ statusOptions.find(o => o.value === status)?.label }}
                        </span>
                        <span v-if="fromStoreId" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            From store selected
                        </span>
                        <span v-if="toStoreId" class="inline-flex items-center px-2 py-1 bg-gray-100 text-sm rounded-md">
                            To store selected
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

            <!-- Transfers Table -->
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
                        Showing {{ transfers.from }} to {{ transfers.to }} of {{ transfers.total }} results
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Transfer #
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    From → To
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Items
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expected
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Progress
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="transfer in transfers.data" :key="transfer.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ transfer.transfer_number }}</div>
                                    <div class="text-xs text-gray-500">by {{ transfer.creator?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ transfer.from_store?.name }}</div>
                                    <div class="text-xs text-gray-500">→</div>
                                    <div class="text-sm text-gray-900">{{ transfer.to_store?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ transfer.items.length }} items</div>
                                    <div class="text-xs text-gray-500">
                                        {{ transfer.items.reduce((sum, item) => sum + item.quantity_requested, 0) }} total
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDateTime(transfer.created_at) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(transfer.expected_delivery_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span
                                        :class="['px-2 py-1 text-xs rounded-full inline-flex items-center', getStatusBadgeClass(transfer.status)]"
                                    >
                                        <component :is="getStatusIcon(transfer.status)" class="w-3 h-3 mr-1" />
                                        {{ transfer.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full"
                                                :class="{
                                                    'bg-yellow-500': transfer.status === 'PENDING',
                                                    'bg-blue-500': transfer.status === 'PROCESSING',
                                                    'bg-purple-500': transfer.status === 'SHIPPED',
                                                    'bg-green-500': transfer.status === 'RECEIVED',
                                                    'bg-red-500': transfer.status === 'CANCELLED'
                                                }"
                                                :style="{ width: `${calculateProgress(transfer)}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-xs text-gray-600">{{ calculateProgress(transfer) }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    <button
                                        @click="viewTransfer(transfer.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        title="View Details"
                                    >
                                        <EyeIcon class="w-5 h-5" />
                                    </button>
                                    <button
                                        v-if="canApprove(transfer)"
                                        @click="approveTransfer(transfer.id)"
                                        class="text-green-600 hover:text-green-900"
                                        title="Approve"
                                    >
                                        <CheckCircleSolid class="w-5 h-5" />
                                    </button>
                                    <button
                                        v-if="canReceive(transfer)"
                                        @click="receiveTransfer(transfer.id)"
                                        class="text-blue-600 hover:text-blue-900"
                                        title="Receive"
                                    >
                                        <TruckIcon class="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="transfers.data.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <ArrowPathIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">No transfers found</h3>
                                    <p class="text-gray-500 mb-4">Try adjusting your filters or create a new transfer</p>
                                    <div class="flex justify-center space-x-3">
                                        <button
                                            v-if="activeFiltersCount > 0"
                                            @click="clearFilters"
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                        >
                                            Clear Filters
                                        </button>
                                        <button
                                            @click="createTransfer"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm text-sm font-medium hover:bg-indigo-700"
                                        >
                                            <PlusIcon class="w-4 h-4 mr-2" />
                                            New Transfer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="transfers.last_page > 1" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Page {{ transfers.current_page }} of {{ transfers.last_page }}
                    </div>
                    <div class="flex space-x-2">
                        <button
                            @click="goToPage(1)"
                            :disabled="transfers.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            First
                        </button>
                        <button
                            @click="goToPage(transfers.current_page - 1)"
                            :disabled="transfers.current_page === 1"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Previous
                        </button>
                        <span class="px-3 py-1 text-sm bg-indigo-50 text-indigo-600 border border-indigo-200 rounded-md">
                            {{ transfers.current_page }}
                        </span>
                        <button
                            @click="goToPage(transfers.current_page + 1)"
                            :disabled="transfers.current_page === transfers.last_page"
                            class="px-3 py-1 border rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                        >
                            Next
                        </button>
                        <button
                            @click="goToPage(transfers.last_page)"
                            :disabled="transfers.current_page === transfers.last_page"
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
                            <p class="text-sm text-gray-600">Total Transfers</p>
                            <p class="text-2xl font-bold text-gray-900">{{ transfers.total }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <ArrowPathIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-yellow-600">
                                {{ transfers.data.filter(t => t.status === 'PENDING').length }}
                            </p>
                        </div>
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <ClockIcon class="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">In Transit</p>
                            <p class="text-2xl font-bold text-purple-600">
                                {{ transfers.data.filter(t => t.status === 'SHIPPED' || t.status === 'PROCESSING').length }}
                            </p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <TruckIcon class="w-6 h-6 text-purple-600" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-green-600">
                                {{ transfers.data.filter(t => t.status === 'RECEIVED').length }}
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <CheckCircleIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>