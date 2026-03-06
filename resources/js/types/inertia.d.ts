import { PageProps as InertiaPageProps } from '@inertiajs/core'
import { Config } from 'ziggy-js'

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps {
        auth: {
            user: {
                id: number
                name: string
                email: string
                roles: string[]
                permissions: string[]
                branch?: {
                    id: number
                    name: string
                    code: string
                }
                store?: {
                    id: number
                    name: string
                    code: string
                }
            }
        }
        ziggy: Config & { location: string }
        flash: {
            message?: string
            error?: string
        }
    }
}