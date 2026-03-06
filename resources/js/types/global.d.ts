import { AxiosInstance } from 'axios'
import { route as ziggyRoute } from 'ziggy-js'

import { User } from './index'

export {}

declare global {
    interface Window {
        axios: AxiosInstance
    }

    var route: typeof ziggyRoute
}

// Vue component type
declare module '*.vue' {
    import type { DefineComponent } from 'vue'
    const component: DefineComponent<{}, {}, any>
    export default component
}


declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: User | null
        }
        flash: {
            message?: string
            error?: string
            success?: string
        }
        ziggy: any
    }
}