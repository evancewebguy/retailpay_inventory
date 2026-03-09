<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import {
    ArrowPathIcon,
    CheckCircleIcon,
    XCircleIcon,
    TruckIcon,
    ClockIcon,
    DocumentTextIcon,
    ChevronLeftIcon,
    UserIcon,
    BuildingStorefrontIcon,
    CalendarIcon,
    CheckBadgeIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps<{
    transfer: {
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
            status: 'PENDING' | 'SHIPPED' | 'RECEIVED' | 'PARTIAL'
            product: {
                id: number
                name: string
                sku: string
                unit: string
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
        receiver?: {
            id: number
            name: string
        } | null
    }
    canApprove: boolean
    canReceive: boolean
}>()

// Receive modal state
const showReceiveModal = ref(false)
const receiving = ref(false)
const receiveItems = ref<Record<number, number>>({})

// Initialize receive quantities
const initializeReceiveQuantities = () => {
    const quantities: Record<number, number> = {}
    props.transfer.items.forEach(item => {
        // Default to the shipped quantity, or requested if not shipped
        quantities[item.product_id] = item.quantity_shipped || item.quantity_requested
    })
    receiveItems.value = quantities
}

// Open receive modal
const openReceiveModal = () => {
    initializeReceiveQuantities()
    showReceiveModal.value = true
}

// Get status badge class
const getStatusBadgeClass = (status: string) => {
    const classes: Record<string, string> = {
        PENDING: 'bg-yellow-100 text-yellow-800',
        PROCESSING: 'bg-blue-100 text-blue-800',
        SHIPPED: 'bg-purple-100 text-purple-800',
        RECEIVED: 'bg-green-100 text-green-800',
        CANCELLED: 'bg-red-100 text-red-800'
    }
    return classes[status] || 'bg-gray-100 text-gray-800'
}

// Get status icon
const getStatusIcon = (status: string) => {
    const icons: Record<string, any> = {
        PENDING: ClockIcon,
        PROCESSING: ArrowPathIcon,
        SHIPPED: TruckIcon,
        RECEIVED: CheckCircleIcon,
        CANCELLED: XCircleIcon
    }
    return icons[status] || ClockIcon
}

// Get item status badge
const getItemStatusBadge = (status: string) => {
    const classes: Record<string, string> = {
        PENDING: 'bg-yellow-100 text-yellow-800',
        SHIPPED: 'bg-blue-100 text-blue-800',
        RECEIVED: 'bg-green-100 text-green-800',
        PARTIAL: 'bg-orange-100 text-orange-800'
    }
    return classes[status] || 'bg-gray-100 text-gray-800'
}

// Format date
const formatDate = (date: string | null) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    })
}

// Format datetime
const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Calculate progress
const calculateProgress = () => {
    const totalItems = props.transfer.items.length
    if (totalItems === 0) return 0
    
    const receivedItems = props.transfer.items.filter(
        item => item.status === 'RECEIVED' || (item.quantity_received && item.quantity_received > 0)
    ).length
    
    return Math.round((receivedItems / totalItems) * 100)
}

// Actions
const approveTransfer = () => {
    if (!confirm('Are you sure you want to approve this transfer?')) return
    
    router.post(`/transfers/${props.transfer.id}/approve`, {}, {
        onSuccess: () => {
            toast.success('Transfer approved successfully')
        },
        onError: () => {
            toast.error('Failed to approve transfer')
        }
    })
}

const receiveTransfer = () => {
    const items = props.transfer.items.map(item => ({
        product_id: item.product_id,
        quantity_received: receiveItems.value[item.product_id] || 0
    }))

    // Validate that at least some items are being received
    if (items.every(item => item.quantity_received === 0)) {
        toast.error('Please enter quantities to receive')
        return
    }

    receiving.value = true
    
    router.post(`/transfers/${props.transfer.id}/receive`, { items }, {
        onSuccess: () => {
            toast.success('Transfer received successfully')
            showReceiveModal.value = false
        },
        onError: (errors) => {
            console.error('Receive failed:', errors)
            toast.error('Failed to receive transfer')
        },
        onFinish: () => {
            receiving.value = false
        }
    })
}

const cancelTransfer = () => {
    if (!confirm('Are you sure you want to cancel this transfer?')) return
    
    router.post(`/transfers/${props.transfer.id}/cancel`, {}, {
        onSuccess: () => {
            toast.success('Transfer cancelled')
        },
        onError: () => {
            toast.error('Failed to cancel transfer')
        }
    })
}

// Format currency (if needed)
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value)
}
</script>

<template>
    <Head :title="`Transfer #${transfer.transfer_number}`" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header with back button -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        href="/transfers"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        <ChevronLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <div class="flex items-center space-x-3">
                            <h1 class="text-2xl font-bold text-gray-900">
                                Transfer #{{ transfer.transfer_number }}
                            </h1>
                            <span
                                :class="['px-3 py-1 text-sm rounded-full flex items-center', getStatusBadgeClass(transfer.status)]"
                            >
                                <component :is="getStatusIcon(transfer.status)" class="w-4 h-4 mr-1" />
                                {{ transfer.status }}
                            </span>
                        </div>
                        <p class="text-gray-600">
                            Created {{ formatDateTime(transfer.created_at) }} by {{ transfer.creator?.name }}
                        </p>
                    </div>
                </div>
                
                <!-- Action buttons -->
                <div class="flex space-x-3">
                    <button
                        v-if="canApprove"
                        @click="approveTransfer"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center"
                    >
                        <CheckBadgeIcon class="w-5 h-5 mr-2" />
                        Approve
                    </button>
                    
                    <button
                        v-if="canReceive"
                        @click="openReceiveModal"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center"
                    >
                        <TruckIcon class="w-5 h-5 mr-2" />
                        Receive
                    </button>
                    
                    <button
                        v-if="transfer.status === 'PENDING'"
                        @click="cancelTransfer"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center"
                    >
                        <XCircleIcon class="w-5 h-5 mr-2" />
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Progress bar -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Transfer Progress</h2>
                    <span class="text-sm font-medium text-gray-600">{{ calculateProgress() }}% Complete</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div
                        class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500"
                        :style="{ width: `${calculateProgress()}%` }"
                    ></div>
                </div>
            </div>

            <!-- Store Information Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Source Store -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 bg-red-100 rounded-lg mr-3">
                            <BuildingStorefrontIcon class="w-6 h-6 text-red-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Source Store</p>
                            <h3 class="text-xl font-bold text-gray-900">{{ transfer.from_store?.name }}</h3>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <p><span class="font-medium">Code:</span> {{ transfer.from_store?.code }}</p>
                        <p><span class="font-medium">Branch:</span> {{ transfer.from_store?.branch?.name }}</p>
                    </div>
                </div>

                <!-- Destination Store -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 bg-green-100 rounded-lg mr-3">
                            <BuildingStorefrontIcon class="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Destination Store</p>
                            <h3 class="text-xl font-bold text-gray-900">{{ transfer.to_store?.name }}</h3>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <p><span class="font-medium">Code:</span> {{ transfer.to_store?.code }}</p>
                        <p><span class="font-medium">Branch:</span> {{ transfer.to_store?.branch?.name }}</p>
                    </div>
                </div>
            </div>

            <!-- Transfer Details -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Transfer Details</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">Expected Delivery</p>
                        <p class="text-base font-medium flex items-center">
                            <CalendarIcon class="w-4 h-4 mr-1 text-gray-400" />
                            {{ formatDate(transfer.expected_delivery_date) }}
                        </p>
                    </div>
                    
                    <div v-if="transfer.approver">
                        <p class="text-sm text-gray-500">Approved By</p>
                        <p class="text-base font-medium flex items-center">
                            <UserIcon class="w-4 h-4 mr-1 text-gray-400" />
                            {{ transfer.approver.name }}
                        </p>
                    </div>
                    
                    <div v-if="transfer.receiver">
                        <p class="text-sm text-gray-500">Received By</p>
                        <p class="text-base font-medium flex items-center">
                            <UserIcon class="w-4 h-4 mr-1 text-gray-400" />
                            {{ transfer.receiver.name }}
                        </p>
                    </div>
                </div>

                <div v-if="transfer.notes" class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Notes:</p>
                    <p class="text-gray-700">{{ transfer.notes }}</p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Items</h2>
                </div>
                
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Requested</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Shipped</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Received</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="item in transfer.items" :key="item.id">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ item.product.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ item.product.sku }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                {{ item.quantity_requested }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                {{ item.quantity_shipped || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                {{ item.quantity_received || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span
                                    :class="['px-2 py-1 text-xs rounded-full', getItemStatusBadge(item.status)]"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Receive Modal -->
            <div v-if="showReceiveModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-900">Receive Transfer #{{ transfer.transfer_number }}</h2>
                        <button @click="showReceiveModal = false" class="text-gray-500 hover:text-gray-700">
                            <XCircleIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div v-for="item in transfer.items" :key="item.id" class="border rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-medium">{{ item.product.name }}</h3>
                                    <p class="text-sm text-gray-500">SKU: {{ item.product.sku }}</p>
                                </div>
                                <span
                                    :class="['px-2 py-1 text-xs rounded-full', getItemStatusBadge(item.status)]"
                                >
                                    {{ item.status }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4 mt-2">
                                <div>
                                    <p class="text-xs text-gray-500">Requested</p>
                                    <p class="font-medium">{{ item.quantity_requested }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Shipped</p>
                                    <p class="font-medium">{{ item.quantity_shipped || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Receive</p>
                                    <input
                                        type="number"
                                        v-model.number="receiveItems[item.product_id]"
                                        :max="item.quantity_shipped || item.quantity_requested"
                                        min="0"
                                        class="w-24 border border-gray-300 rounded-lg px-2 py-1"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            @click="showReceiveModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            @click="receiveTransfer"
                            :disabled="receiving"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center"
                        >
                            <ArrowPathIcon v-if="receiving" class="w-4 h-4 animate-spin mr-2" />
                            {{ receiving ? 'Processing...' : 'Confirm Receipt' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>