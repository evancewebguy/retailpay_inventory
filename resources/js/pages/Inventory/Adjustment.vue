<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, computed, watch, nextTick } from 'vue'
import { useToast } from 'vue-toastification'
import axios from 'axios'
import {
    ArrowPathIcon,
    PlusIcon,
    TrashIcon,
    DocumentTextIcon,
    InformationCircleIcon,
    ChevronLeftIcon,
    MagnifyingGlassIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()

// Define props from the controller
const props = defineProps<{
    stores: Array<{
        id: number
        name: string
        code: string
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
        reorder_level: number
    }>
    selected_store?: number
    selected_product?: number
}>()

// Form state using Inertia's useForm
const form = useForm({
    store_id: props.selected_store?.toString() || '',
    type: 'CORRECTION',
    reason: 'COUNT_CORRECTION',
    notes: '',
    items: [
        {
            product_id: props.selected_product?.toString() || '',
            new_quantity: 0,
            reason: '',
            current_quantity: 0
        }
    ],
})

// Loading states
const checkingCurrentStock = ref(false)
const productSearch = ref('')
const showProductDropdown = ref(false)
const activeDropdownIndex = ref<number | null>(null)

// Current stock values for selected products
const currentStockValues = ref<Record<number, number>>({})

// Adjustment types
const adjustmentTypes = [
    { value: 'ADDITION', label: 'Addition', description: 'Add stock to inventory' },
    { value: 'REDUCTION', label: 'Reduction', description: 'Remove stock from inventory' },
    { value: 'CORRECTION', label: 'Correction', description: 'Correct inventory count' },
]

// Reasons based on type
const getReasons = (type: string) => {
    const reasons = {
        ADDITION: [
            { value: 'PROCUREMENT', label: 'Procurement/Purchase' },
            { value: 'RETURN', label: 'Customer Return' },
            { value: 'FOUND', label: 'Found Stock' },
            { value: 'OTHER', label: 'Other' },
        ],
        REDUCTION: [
            { value: 'DAMAGE', label: 'Damaged Goods' },
            { value: 'LOST', label: 'Lost/Theft' },
            { value: 'EXPIRY', label: 'Expired' },
            { value: 'OTHER', label: 'Other' },
        ],
        CORRECTION: [
            { value: 'COUNT_CORRECTION', label: 'Physical Count Correction' },
            { value: 'SYSTEM_ERROR', label: 'System Error Correction' },
            { value: 'OTHER', label: 'Other' },
        ],
    }
    return reasons[type as keyof typeof reasons] || reasons.CORRECTION
}

// Filtered products based on search
const filteredProducts = computed(() => {
    if (!productSearch.value) return props.products
    
    const search = productSearch.value.toLowerCase()
    return props.products.filter(product => 
        product.name.toLowerCase().includes(search) ||
        product.sku.toLowerCase().includes(search)
    )
})

// Computed properties
const selectedStore = computed(() => {
    return props.stores.find(s => s.id === Number(form.store_id))
})

const canSubmit = computed(() => {
    return form.store_id && 
           form.type && 
           form.reason && 
           form.items.length > 0 && 
           form.items.every(item => item.product_id && Number(item.new_quantity) >= 0) &&
           !form.processing
})

// Methods
const addItem = () => {
    form.items.push({
        product_id: '',
        new_quantity: 0,
        reason: '',
        current_quantity: 0
    })
}

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        const productId = form.items[index].product_id
        form.items.splice(index, 1)
        // Remove from current stock values
        if (productId) {
            const newValues = { ...currentStockValues.value }
            delete newValues[Number(productId)]
            currentStockValues.value = newValues
        }
    }
}

const getProductDetails = (productId: number) => {
    return props.products.find(p => p.id === productId)
}

// Load current stock when product is selected
const loadCurrentStock = async (productId: number, index: number) => {
    if (!form.store_id || !productId) return
    
    checkingCurrentStock.value = true
    try {
        // Use POST instead of GET to avoid route conflicts
        const response = await axios.post('/api/inventory/check-single-availability', {
            store_id: Number(form.store_id),
            product_id: productId,
            quantity: 1
        })
        
        const data = response.data.data
        const onHandQuantity = data.on_hand_quantity || 0
        
        // Store in reactive ref
        currentStockValues.value[productId] = onHandQuantity
        
        // Update the item's current quantity
        form.items[index].current_quantity = onHandQuantity
        
    } catch (error: any) {
        console.error('Failed to load current stock:', error)
        if (error.response?.status === 404) {
            toast.error('Product not found in this store')
        } else {
            toast.error('Failed to load current stock')
        }
    } finally {
        checkingCurrentStock.value = false
    }
}

// Calculate change
const calculateChange = (item: any) => {
    const current = currentStockValues.value[Number(item.product_id)] || 0
    const newQty = Number(item.new_quantity) || 0
    return newQty - current
}

// Get change class
const getChangeClass = (item: any) => {
    const change = calculateChange(item)
    if (change > 0) return 'text-green-600'
    if (change < 0) return 'text-red-600'
    return 'text-gray-600'
}

// Get change sign
const getChangeSign = (item: any) => {
    const change = calculateChange(item)
    if (change > 0) return '+'
    if (change < 0) return '-'
    return ''
}

// Select product from dropdown
const selectProduct = (product: any, index: number) => {
    form.items[index].product_id = product.id.toString()
    productSearch.value = ''
    showProductDropdown.value = false
    activeDropdownIndex.value = null
    loadCurrentStock(product.id, index)
}


// Watch for product selection
watch(() => form.items.map(item => item.product_id), (newProductIds) => {
    newProductIds.forEach((productId, index) => {
        if (productId && !currentStockValues.value[Number(productId)]) {
            loadCurrentStock(Number(productId), index)
        }
    })
}, { deep: true })

// Watch for store change - reset current stock values
watch(() => form.store_id, () => {
    currentStockValues.value = {}
    form.items.forEach((item, index) => {
        if (item.product_id) {
            loadCurrentStock(Number(item.product_id), index)
        }
    })
})

// Submit form
const submit = () => {
    // Prepare data for submission
    const submitData = {
        store_id: Number(form.store_id),
        type: form.type,
        reason: form.reason,
        notes: form.notes,
        items: form.items.map(item => ({
            product_id: Number(item.product_id),
            new_quantity: Number(item.new_quantity),
            reason: item.reason || null
        })).filter(item => item.product_id) // Filter out items without product
    }
    
    console.log('Submitting adjustment:', submitData)
    
    form.transform(() => submitData).post('/inventory/adjustment', {
        onSuccess: () => {
            toast.success('Stock adjustment completed successfully')
            router.visit('/inventory')
        },
        onError: (errors) => {
            console.error('Adjustment failed:', errors)
            if (typeof errors === 'object') {
                Object.entries(errors).forEach(([key, value]) => {
                    toast.error(`${key}: ${value}`)
                })
            } else {
                toast.error('Failed to complete adjustment')
            }
        },
    })
}

// Cancel and go back
const cancel = () => {
    router.visit('/inventory')
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
    <Head title="Stock Adjustment" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        href="/inventory"
                        class="text-gray-600 hover:text-gray-900"
                    >
                        <ChevronLeftIcon class="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Stock Adjustment</h1>
                        <p class="text-gray-600">Manually adjust inventory levels</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Store Selection -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Location</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Store -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Store <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.store_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                :class="{ 'border-red-500': form.errors.store_id }"
                            >
                                <option value="">Select store</option>
                                <option
                                    v-for="store in stores"
                                    :key="store.id"
                                    :value="store.id"
                                >
                                    {{ store.name }} ({{ store.branch?.name }})
                                </option>
                            </select>
                            <p v-if="form.errors.store_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.store_id }}
                            </p>
                            <p v-if="selectedStore" class="mt-1 text-xs text-gray-500">
                                Code: {{ selectedStore.code }} | Branch: {{ selectedStore.branch?.name }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Adjustment Details -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Adjustment Details</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Adjustment Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Adjustment Type <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                :class="{ 'border-red-500': form.errors.type }"
                            >
                                <option value="">Select type</option>
                                <option
                                    v-for="type in adjustmentTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                                {{ form.errors.type }}
                            </p>
                            <p v-if="form.type" class="mt-1 text-xs text-gray-500">
                                {{ adjustmentTypes.find(t => t.value === form.type)?.description }}
                            </p>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.reason"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                :class="{ 'border-red-500': form.errors.reason }"
                            >
                                <option value="">Select reason</option>
                                <option
                                    v-for="reason in getReasons(form.type)"
                                    :key="reason.value"
                                    :value="reason.value"
                                >
                                    {{ reason.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.reason" class="mt-1 text-sm text-red-600">
                                {{ form.errors.reason }}
                            </p>
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
                                    rows="2"
                                    placeholder="Add any additional notes about this adjustment..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Section -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Items to Adjust</h2>
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

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <!-- Product Select with Search -->
                                <div class="md:col-span-5 relative">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Product <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                                        <input
                                            type="text"
                                            v-model="productSearch"
                                            @focus="showProductDropdown = true; activeDropdownIndex = index"
                                            @blur="setTimeout(() => showProductDropdown = false, 200)"
                                            :placeholder="item.product_id ? getProductDetails(Number(item.product_id))?.name : 'Search product...'"
                                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                    </div>
                                    
                                    <!-- Product Dropdown -->
                                    <div
                                        v-if="showProductDropdown && activeDropdownIndex === index && filteredProducts.length > 0"
                                        class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                                    >
                                        <div
                                            v-for="product in filteredProducts"
                                            :key="product.id"
                                            @mousedown="selectProduct(product, index)"
                                            class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                        >
                                            <div class="font-medium">{{ product.name }}</div>
                                            <div class="text-xs text-gray-500">SKU: {{ product.sku }} | Price: {{ formatCurrency(product.selling_price) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Current Stock -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Current Stock
                                    </label>
                                    <div class="h-10 px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 flex items-center">
                                        <span v-if="checkingCurrentStock" class="text-gray-400">
                                            <ArrowPathIcon class="w-4 h-4 animate-spin" />
                                        </span>
                                        <span v-else>
                                            {{ currentStockValues[Number(item.product_id)] || 0 }}
                                        </span>
                                    </div>
                                </div>

                                <!-- New Quantity -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        New Quantity <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model.number="item.new_quantity"
                                        type="number"
                                        min="0"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        :class="{ 'border-red-500': form.errors[`items.${index}.new_quantity`] }"
                                    />
                                    <p v-if="form.errors[`items.${index}.new_quantity`]" class="mt-1 text-sm text-red-600">
                                        {{ form.errors[`items.${index}.new_quantity`] }}
                                    </p>
                                </div>

                                <!-- Change -->
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Change
                                    </label>
                                    <div class="h-10 px-3 py-2 border border-gray-300 rounded-lg flex items-center"
                                         :class="getChangeClass(item)">
                                        <span class="font-medium">
                                            {{ getChangeSign(item) }}{{ Math.abs(calculateChange(item)) }}
                                        </span>
                                        <span class="text-xs text-gray-500 ml-2">
                                            ({{ calculateChange(item) > 0 ? 'Addition' : calculateChange(item) < 0 ? 'Reduction' : 'No change' }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Item-specific reason -->
                            <div class="mt-2">
                                <input
                                    v-model="item.reason"
                                    type="text"
                                    placeholder="Reason for this item (optional)"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Items Error -->
                    <p v-if="form.errors.items" class="mt-2 text-sm text-red-600">
                        {{ form.errors.items }}
                    </p>
                </div>

                <!-- Summary -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Adjustment Summary</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900">{{ form.items.length }}</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Total Additions</p>
                            <p class="text-2xl font-bold text-green-600">
                                {{ form.items.reduce((sum, item) => {
                                    const change = calculateChange(item)
                                    return sum + (change > 0 ? change : 0)
                                }, 0) }}
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Total Reductions</p>
                            <p class="text-2xl font-bold text-red-600">
                                {{ form.items.reduce((sum, item) => {
                                    const change = calculateChange(item)
                                    return sum + (change < 0 ? Math.abs(change) : 0)
                                }, 0) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Warning Message -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex">
                        <ExclamationTriangleIcon class="w-5 h-5 text-yellow-600 mr-2 flex-shrink-0" />
                        <div class="text-sm text-yellow-800">
                            <p class="font-medium mb-1">Important:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Stock adjustments are permanent and cannot be undone</li>
                                <li>All adjustments are logged for audit purposes</li>
                                <li>A reason must be provided for each adjustment</li>
                            </ul>
                        </div>
                    </div>
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
                        {{ form.processing ? 'Processing...' : 'Complete Adjustment' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>