<script setup lang="ts">

import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed, watch } from 'vue'
import { useToast } from 'vue-toastification'
import axios from 'axios'

import {
    ArrowPathIcon,
    PlusIcon,
    TrashIcon,
    CalendarIcon,
    DocumentTextIcon,
    InformationCircleIcon,
    ChevronLeftIcon,
    CheckCircleIcon,
    XCircleIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

// Define props from the controller
const props = defineProps<{
    sourceStores: Array<{
        id: number
        name: string
        code: string
        branch_id: number
        branch?: {
            id: number
            name: string
        }
    }>
    destinationStores: Array<{
        id: number
        name: string
        code: string
        branch_id: number
        branch?: {
            id: number
            name: string
        }
    }>
    products: Array<{
        id: number
        name: string
        sku: string
        selling_price: number
        cost_price: number
        unit: string
    }>
    selectedSource?: number
    selectedDestination?: number
}>()

// Form state using Inertia's useForm
const form = useForm({
    from_store_id: props.selectedSource?.toString() || '',
    to_store_id: props.selectedDestination?.toString() || '',
    expected_delivery_date: '',
    notes: '',
    items: [
        {
            product_id: '',
            quantity: 1,
        }
    ],
})

// Loading states
const checkingAvailability = ref(false)

const availabilityResults = ref<Record<number, {
    product_id: number
    product_name: string
    requested_quantity: number
    available_quantity: number
    status: 'ok' | 'low' | 'insufficient'
    can_fulfill: boolean
}>>({})

// Date validation
const minDate = new Date().toISOString().split('T')[0]

// Transfer type detection
const transferType = computed(() => {
    if (!form.from_store_id || !form.to_store_id) return null
    
    const fromStore = props.sourceStores.find(s => s.id === Number(form.from_store_id))
    const toStore = props.destinationStores.find(s => s.id === Number(form.to_store_id))
    
    if (!fromStore || !toStore) return null
    
    return fromStore.branch_id === toStore.branch_id ? 'inter_store' : 'inter_branch'
})

const transferTypeInfo = computed(() => {
    if (!transferType.value) return null
    
    return transferType.value === 'inter_store'
        ? {
            label: 'Same Branch Transfer',
            description: 'Transferring within the same branch',
            class: 'bg-green-50 border-green-200 text-green-700',
            icon: CheckCircleIcon,
            iconClass: 'text-green-500'
        }
        : {
            label: 'Cross-Branch Transfer',
            description: 'Transferring between different branches (may require additional approval)',
            class: 'bg-purple-50 border-purple-200 text-purple-700',
            icon: ExclamationTriangleIcon,
            iconClass: 'text-purple-500'
        }
})

// Computed properties
const selectedSourceStore = computed(() => {
    return props.sourceStores.find(s => s.id === Number(form.from_store_id))
})

const selectedDestinationStore = computed(() => {
    return props.destinationStores.find(s => s.id === Number(form.to_store_id))
})

const totalItems = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0)
})

const canSubmit = computed(() => {
    return form.from_store_id && 
           form.to_store_id && 
           form.items.length > 0 && 
           form.items.every(item => item.product_id && Number(item.quantity) > 0) &&
           !form.processing
})

const allItemsAvailable = computed(() => {
    if (!availabilityResults.value || Object.keys(availabilityResults.value).length === 0) return false
    return Object.values(availabilityResults.value).every(result => result?.can_fulfill)
})

// Methods
const addItem = () => {
    form.items.push({
        product_id: '',
        quantity: 1,
    })
}

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        const removedItem = form.items[index]
        form.items.splice(index, 1)
        
        // Remove from availability results
        if (removedItem.product_id) {
            const newResults = { ...availabilityResults.value }
            delete newResults[Number(removedItem.product_id)]
            availabilityResults.value = newResults
        }
    }
}

const getProductDetails = (productId: number) => {
    return props.products.find(p => p.id === productId)
}

// Check stock availability
const checkAvailability = async () => {
    if (!form.from_store_id) {
        toast.warning('Please select a source store first')
        return
    }
    
    const itemsToCheck = form.items
        .filter(item => item.product_id)
        .map(item => ({
            product_id: Number(item.product_id),
            quantity: Number(item.quantity)
        }))
    
    if (itemsToCheck.length === 0) {
        toast.warning('Please add items to check')
        return
    }
    
    checkingAvailability.value = true
    try {
        const response = await axios.post('/api/inventory/check-availability', {
            store_id: Number(form.from_store_id),
            items: itemsToCheck
        })
        
        console.log('Availability response:', response.data)
        
        // Store results
        availabilityResults.value = response.data.data || {}
        
        // Show summary
        const summary = response.data.summary
        if (summary) {
            if (summary.all_available) {
                toast.success('✅ All items are available in stock')
            } else if (summary.partially_available) {
                toast.warning(`⚠️ ${summary.items_with_insufficient_stock} item(s) have insufficient stock`, {
                    timeout: 5000
                })
            } else {
                toast.error('❌ Some items are out of stock')
            }
        }
        
        // Show individual item warnings
        if (response.data.insufficient_items?.length > 0) {
            response.data.insufficient_items.forEach((item: any) => {
                toast.error(`${item.product_name}: Requested ${item.requested}, Available ${item.available}`, {
                    timeout: 4000
                })
            })
        }
        
    } catch (error: any) {
        console.error('Availability check failed:', error)
        
        if (error.response) {
            const status = error.response.status
            const data = error.response.data
            
            if (status === 422) {
                toast.error('Validation error: ' + (data.message || 'Invalid data'))
            } else if (status === 403) {
                toast.error('You do not have permission to check inventory')
            } else if (status === 404) {
                toast.error('Store or products not found')
            } else {
                toast.error(data.message || 'Failed to check availability')
            }
        } else if (error.request) {
            toast.error('Network error - please check your connection')
        } else {
            toast.error('An unexpected error occurred')
        }
    } finally {
        checkingAvailability.value = false
    }
}

// Get stock status message
const getStockStatusMessage = (productId: number, requestedQty: number) => {
    if (!availabilityResults.value) return null
    
    const availability = availabilityResults.value[productId]
    if (!availability) return null
    
    if (availability.status === 'insufficient') {
        return {
            message: `Insufficient stock. Available: ${availability.available_quantity}`,
            class: 'text-red-600 bg-red-50 p-2 rounded-lg'
        }
    }
    if (availability.status === 'low') {
        return {
            message: `Low stock. Available: ${availability.available_quantity}`,
            class: 'text-yellow-600 bg-yellow-50 p-2 rounded-lg'
        }
    }
    return {
        message: `Available: ${availability.available_quantity}`,
        class: 'text-green-600 bg-green-50 p-2 rounded-lg'
    }
}


// Watch for changes that affect availability
watch([() => form.from_store_id, () => form.items], () => {
    // Debounce to avoid too many requests
    const timeout = setTimeout(() => {
        checkAvailability()
    }, 500)
    
    return () => clearTimeout(timeout)
}, { deep: true })

// Submit form
const submit = () => {
    // Validate items have stock
    if (!allItemsAvailable.value && Object.keys(availabilityResults.value).length > 0) {
        if (!confirm('Some items have insufficient stock. Continue anyway?')) {
            return
        }
    }
    
    form.post('/transfers', {
        onSuccess: () => {
            toast.success('Transfer created successfully')
        },
        onError: (errors) => {
            console.error('Transfer creation failed:', errors)
            toast.error('Failed to create transfer')
        },
    })
}

// Cancel and go back
const cancel = () => {
    router.visit('/transfers')
}

// Format currency
const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(value)
}
</script>

<template>
    <Head title="Create Transfer" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        href="/transfers"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        <ChevronLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Create New Transfer</h1>
                        <p class="text-gray-600">Transfer stock between stores</p>
                    </div>
                </div>
                
                <!-- Check Availability Button -->
                <button
                    type="button"
                    @click="checkAvailability"
                    :disabled="checkingAvailability || !form.from_store_id || form.items.length === 0"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                >
                    <ArrowPathIcon v-if="checkingAvailability" class="w-4 h-4 animate-spin mr-2" />
                    {{ checkingAvailability ? 'Checking...' : 'Check Availability' }}
                </button>
            </div>

            <!-- Transfer Type Indicator -->
            <div v-if="transferTypeInfo" 
                 :class="['border rounded-lg p-4', transferTypeInfo.class]">
                <div class="flex items-center">
                    <component :is="transferTypeInfo.icon" :class="['w-5 h-5 mr-3', transferTypeInfo.iconClass]" />
                    <div>
                        <h3 class="font-medium">{{ transferTypeInfo.label }}</h3>
                        <p class="text-sm opacity-75">{{ transferTypeInfo.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Store Selection -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Store Information</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Source Store -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Source Store <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.from_store_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                :class="{ 'border-red-500': form.errors.from_store_id }"
                            >
                                <option value="">Select source store</option>
                                <option
                                    v-for="store in sourceStores"
                                    :key="store.id"
                                    :value="store.id"
                                >
                                    {{ store.name }} ({{ store.branch?.name }})
                                </option>
                            </select>
                            <p v-if="form.errors.from_store_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.from_store_id }}
                            </p>
                            <p v-if="selectedSourceStore" class="mt-1 text-xs text-gray-500">
                                Code: {{ selectedSourceStore.code }} | Branch: {{ selectedSourceStore.branch?.name }}
                            </p>
                        </div>

                        <!-- Destination Store -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Destination Store <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.to_store_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                :class="{ 'border-red-500': form.errors.to_store_id }"
                            >
                                <option value="">Select destination store</option>
                                <option
                                    v-for="store in destinationStores"
                                    :key="store.id"
                                    :value="store.id"
                                    :disabled="store.id === Number(form.from_store_id)"
                                >
                                    {{ store.name }} ({{ store.branch?.name }})
                                </option>
                            </select>
                            <p v-if="form.errors.to_store_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.to_store_id }}
                            </p>
                            <p v-if="selectedDestinationStore" class="mt-1 text-xs text-gray-500">
                                Code: {{ selectedDestinationStore.code }} | Branch: {{ selectedDestinationStore.branch?.name }}
                            </p>
                        </div>

                        <!-- Expected Delivery Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Expected Delivery Date
                            </label>
                            <div class="relative">
                                <CalendarIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                                <input
                                    v-model="form.expected_delivery_date"
                                    type="date"
                                    :min="minDate"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Notes
                            </label>
                            <div class="relative">
                                <DocumentTextIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Add any additional notes about this transfer..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Items to Transfer</h2>
                        <button
                            type="button"
                            @click="addItem"
                            class="text-indigo-600 hover:text-indigo-900 text-sm flex items-center"
                        >
                            <PlusIcon class="w-4 h-4 mr-1" />
                            Add Item
                        </button>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="border border-gray-200 rounded-lg p-4"
                        >
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-sm font-medium text-gray-700">Item #{{ index + 1 }}</h3>
                                <button
                                    v-if="form.items.length > 1"
                                    type="button"
                                    @click="removeItem(index)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Product Select -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Product <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="item.product_id"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        :class="{ 'border-red-500': form.errors[`items.${index}.product_id`] }"
                                    >
                                        <option value="">Select product</option>
                                        <option
                                            v-for="product in products"
                                            :key="product.id"
                                            :value="product.id"
                                        >
                                            {{ product.name }} ({{ product.sku }}) - {{ formatCurrency(product.selling_price) }} per {{ product.unit }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors[`items.${index}.product_id`]" class="mt-1 text-sm text-red-600">
                                        {{ form.errors[`items.${index}.product_id`] }}
                                    </p>
                                </div>

                                <!-- Quantity -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Quantity <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        :class="{ 'border-red-500': form.errors[`items.${index}.quantity`] }"
                                    />
                                    <p v-if="form.errors[`items.${index}.quantity`]" class="mt-1 text-sm text-red-600">
                                        {{ form.errors[`items.${index}.quantity`] }}
                                    </p>
                                </div>
                            </div>

                            <!-- Stock Availability Info -->
                            <div v-if="item.product_id && form.from_store_id" class="mt-3">
                                <div v-if="checkingAvailability" class="text-sm text-gray-500 flex items-center">
                                    <ArrowPathIcon class="w-4 h-4 animate-spin mr-2" />
                                    Checking availability...
                                </div>
                                <div v-else-if="getStockStatusMessage(Number(item.product_id), Number(item.quantity))" 
                                     :class="['text-sm', getStockStatusMessage(Number(item.product_id), Number(item.quantity))?.class]">
                                    {{ getStockStatusMessage(Number(item.product_id), Number(item.quantity))?.message }}
                                </div>
                            </div>

                            <!-- Product Details -->
                            <div v-if="item.product_id" class="mt-2 text-xs text-gray-500 flex items-center">
                                <InformationCircleIcon class="w-3 h-3 mr-1" />
                                <span>
                                    Unit: {{ getProductDetails(Number(item.product_id))?.unit }} | 
                                    Price: {{ formatCurrency(getProductDetails(Number(item.product_id))?.selling_price || 0) }} |
                                    SKU: {{ getProductDetails(Number(item.product_id))?.sku }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Items Summary -->
                    <div v-if="form.items && form.items.length > 0" class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Total Items:</span>
                            <span class="text-lg font-bold text-indigo-600">{{ totalItems }}</span>
                        </div>
                        <div v-if="availabilityResults && Object.keys(availabilityResults).length > 0" class="mt-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Availability Status:</span>
                                <span :class="allItemsAvailable ? 'text-green-600' : 'text-yellow-600'">
                                    {{ allItemsAvailable ? 'All Available' : 'Some Items Low/Out of Stock' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Items Error -->
                    <p v-if="form.errors.items" class="mt-2 text-sm text-red-600">
                        {{ form.errors.items }}
                    </p>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="cancel"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="!canSubmit || form.processing"
                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
                    >
                        <ArrowPathIcon v-if="form.processing" class="w-4 h-4 animate-spin mr-2" />
                        {{ form.processing ? 'Creating...' : 'Create Transfer' }}
                    </button>
                </div>
            </form>

            <!-- Info Card -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex">
                    <InformationCircleIcon class="w-5 h-5 text-blue-600 mr-2 flex-shrink-0" />
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">Transfer Process:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>After creation, transfers need to be approved by a manager</li>
                            <li>Once approved, items will be reserved from source store inventory</li>
                            <li>When shipped, update the transfer status</li>
                            <li>Upon receipt, the destination store inventory will be updated</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>