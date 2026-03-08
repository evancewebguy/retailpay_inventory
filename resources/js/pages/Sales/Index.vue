<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import { 
    PlusIcon, 
    MagnifyingGlassIcon,
    EyeIcon
} from '@heroicons/vue/24/outline'
import type { Sale, Store, Product } from '@/types'

const toast = useToast()

// State
const sales = ref<{ data: Sale[], current_page: number, last_page: number, total: number }>({
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0
})
const stores = ref<Store[]>([])
const products = ref<Product[]>([])
const customers = ref<any[]>([])

// Filters
const filters = ref({
    store_id: '',
    from_date: '',
    to_date: '',
    search: '',
    per_page: 20
})

// Modal
const showCreateModal = ref(false)
const creating = ref(false)

// New sale form
const newSale = ref({
    store_id: '',
    customer_id: null,
    items: [{ product_id: '', quantity: 1 }]
})

// Computed products map for faster lookup
const productsMap = computed(() => {
    if (!Array.isArray(products.value)) {
        console.warn('Products is not an array:', products.value)
        return new Map()
    }
    
    return new Map(
        products.value.map(p => [p.id, p])
    )
})

// Fetch functions
const fetchSales = async (page = 1) => {
    try {
        const response = await axios.get('/api/sales', {
            params: { ...filters.value, page }
        })
        // Handle different response structures
        if (response.data.data) {
            sales.value = response.data
        } else {
            sales.value = {
                data: response.data,
                current_page: 1,
                last_page: 1,
                total: response.data.length
            }
        }
    } catch (error) {
        console.error('Failed to fetch sales:', error)
        toast.error('Failed to fetch sales')
    }
}

const fetchStores = async () => {
    try {
        const response = await axios.get('/api/stores')
        console.log('Stores response:', response.data)
        
        if (Array.isArray(response.data)) {
            stores.value = response.data
        } else if (response.data.data && Array.isArray(response.data.data)) {
            stores.value = response.data.data
        } else {
            stores.value = []
        }
    } catch (error) {
        console.error('Failed to fetch stores:', error)
        toast.error('Failed to fetch stores')
    }
}

const fetchProducts = async () => {
    try {
        const response = await axios.get('/api/products')
        console.log('Products response:', response.data)
        
        // Handle different response structures
        if (Array.isArray(response.data)) {
            products.value = response.data
        } else if (response.data.data && Array.isArray(response.data.data)) {
            products.value = response.data.data
        } else {
            products.value = []
            console.error('Unexpected products data structure:', response.data)
        }
    } catch (error) {
        console.error('Failed to fetch products:', error)
        toast.error('Failed to fetch products')
    }
}

const fetchCustomers = async () => {
    try {
        const response = await axios.get('/api/customers')
        if (Array.isArray(response.data)) {
            customers.value = response.data
        } else if (response.data.data && Array.isArray(response.data.data)) {
            customers.value = response.data.data
        }
    } catch (error) {
        console.error('Failed to fetch customers:', error)
        // Non-critical error, don't show toast
    }
}

// Form helpers
const addItem = () => {
    newSale.value.items.push({ product_id: '', quantity: 1 })
}

const removeItem = (index: number) => {
    if (newSale.value.items.length > 1) {
        newSale.value.items.splice(index, 1)
    }
}

const calculateTotal = () => {
    // Ensure products is an array
    if (!Array.isArray(products.value) || products.value.length === 0) {
        return 0
    }
    
    return newSale.value.items.reduce((total, item) => {
        if (!item.product_id) return total
        
        const product = products.value.find(p => p && p.id === Number(item.product_id))
        const price = product?.selling_price || 0
        const quantity = Number(item.quantity) || 0
        
        return total + (price * quantity)
    }, 0)
}

// Actions
const createSale = async () => {
    // Validate form
    if (!newSale.value.store_id) {
        toast.error('Please select a store')
        return
    }
    
    const itemsWithProducts = newSale.value.items.filter(item => item.product_id)
    if (itemsWithProducts.length === 0) {
        toast.error('Please add at least one product')
        return
    }
    
    try {
        creating.value = true
        await axios.post('/api/sales', newSale.value)
        toast.success('Sale completed successfully')
        showCreateModal.value = false
        fetchSales()
        // Reset form
        newSale.value = {
            store_id: '',
            customer_id: null,
            items: [{ product_id: '', quantity: 1 }]
        }
    } catch (error: any) {
        console.error('Sale creation error:', error)
        const errorMessage = error.response?.data?.message || error.response?.data?.error || 'Sale failed'
        toast.error(errorMessage)
    } finally {
        creating.value = false
    }
}

const viewSale = (id: number) => {
    router.get(`/sales/${id}`)
}

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>
const handleSearch = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        fetchSales(1)
    }, 500)
}

// Pagination
const goToPage = (page: number) => {
    if (page >= 1 && page <= sales.value.last_page) {
        fetchSales(page)
    }
}

// Open modal function with debugging
const openModal = () => {
    console.log('Opening modal...')
    console.log('Products available:', products.value.length)
    console.log('Stores available:', stores.value.length)
    showCreateModal.value = true
}

// Initial load
onMounted(() => {
    fetchSales()
    fetchStores()
    fetchProducts()
    fetchCustomers()
})
</script>

<template>
    <Head title="Sales" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sales Management</h1>
                    <p class="text-gray-600">Manage and track all sales transactions</p>
                </div>
                <button
                    @click="openModal"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 flex items-center"
                >
                    <PlusIcon class="w-5 h-5 mr-2" />
                    New Sale
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Store</label>
                        <select
                            v-model="filters.store_id"
                            @change="fetchSales(1)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">All Stores</option>
                            <option v-for="store in stores" :key="store.id" :value="store.id">
                                {{ store.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input
                            type="date"
                            v-model="filters.from_date"
                            @change="fetchSales(1)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input
                            type="date"
                            v-model="filters.to_date"
                            @change="fetchSales(1)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="w-5 h-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                type="text"
                                v-model="filters.search"
                                @input="handleSearch"
                                placeholder="Sale # or customer"
                                class="w-full pl-10 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                        <select
                            v-model="filters.per_page"
                            @change="fetchSales(1)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Sale #
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Store
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Items
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total
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
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ sale.sale_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ sale.store?.name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ new Date(sale.created_at).toLocaleDateString() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ sale.items?.length || 0 }} items
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold">
                                ${{ Number(sale.grand_total).toFixed(2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-800': sale.status === 'COMPLETED',
                                        'bg-yellow-100 text-yellow-800': sale.status === 'PENDING',
                                        'bg-red-100 text-red-800': sale.status === 'CANCELLED'
                                    }"
                                >
                                    {{ sale.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <button
                                    @click="viewSale(sale.id)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3"
                                    title="View Details"
                                >
                                    <EyeIcon class="w-5 h-5" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="sales.data.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No sales found
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="sales.last_page > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button
                            @click="goToPage(sales.current_page - 1)"
                            :disabled="sales.current_page === 1"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <button
                            @click="goToPage(sales.current_page + 1)"
                            :disabled="sales.current_page === sales.last_page"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
                        >
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium">{{ ((sales.current_page - 1) * filters.per_page) + 1 }}</span>
                                to
                                <span class="font-medium">{{ Math.min(sales.current_page * filters.per_page, sales.total) }}</span>
                                of
                                <span class="font-medium">{{ sales.total }}</span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <button
                                    @click="goToPage(1)"
                                    :disabled="sales.current_page === 1"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    First
                                </button>
                                <button
                                    @click="goToPage(sales.current_page - 1)"
                                    :disabled="sales.current_page === 1"
                                    class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                    Page {{ sales.current_page }} of {{ sales.last_page }}
                                </span>
                                <button
                                    @click="goToPage(sales.current_page + 1)"
                                    :disabled="sales.current_page === sales.last_page"
                                    class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Next
                                </button>
                                <button
                                    @click="goToPage(sales.last_page)"
                                    :disabled="sales.current_page === sales.last_page"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Last
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Sale Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50" @click.self="showCreateModal = false">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Create New Sale</h2>
                    <button @click="showCreateModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">
                        ×
                    </button>
                </div>
                
                <form @submit.prevent="createSale">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Store *</label>
                        <select
                            v-model="newSale.store_id"
                            required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Select Store</option>
                            <option v-for="store in stores" :key="store.id" :value="store.id">
                                {{ store.name }}
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer (Optional)</label>
                        <select
                            v-model="newSale.customer_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option :value="null">Walk-in Customer</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Items *</label>
                        <div v-for="(item, index) in newSale.items" :key="index" class="flex items-center space-x-2 mb-2">
                            <select
                                v-model="item.product_id"
                                required
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="">Select Product</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} - ${{ product.selling_price }}
                                </option>
                            </select>
                            <input
                                type="number"
                                v-model.number="item.quantity"
                                required
                                min="1"
                                class="w-24 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            <button
                                type="button"
                                @click="removeItem(index)"
                                class="text-red-600 hover:text-red-900"
                                :disabled="newSale.items.length === 1"
                            >
                                ×
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="addItem"
                            class="mt-2 text-indigo-600 hover:text-indigo-900 text-sm"
                        >
                            + Add Item
                        </button>
                    </div>

                    <div class="mb-6 text-right">
                        <p class="text-lg font-bold">Total: ${{ calculateTotal().toFixed(2) }}</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="creating"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center"
                        >
                            <span v-if="creating" class="mr-2">Processing...</span>
                            <span v-else>Complete Sale</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>