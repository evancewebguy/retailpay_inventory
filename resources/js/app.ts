
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import type { DefineComponent } from 'vue'

import '../css/app.css'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'

const appName = import.meta.env.VITE_APP_NAME || 'KK Wholesalers'

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        return resolvePageComponent(`./Pages/${name}.vue`, pages)
    },
    
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
        
        app.use(plugin)

        app.use(Toast, {
            position: 'top-right',
            timeout: 3000,
            closeOnClick: true,
            pauseOnHover: true,
            draggable: true,
        })

        
        app.mount(el)
    },
    
    progress: {
        color: '#4f46e5',
        showSpinner: true,
    },
})