import { addMonths, format, parseISO } from 'date-fns'

const DATETIME_FORMAT = 'd.M.y HH:mm:ss'
const DATE_FORMAT = 'd.M.y'

export function formatDatetime(value: string | Date | null | undefined): string {
    if (!value) return '—'
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, DATETIME_FORMAT)
}

export function formatDate(value: string | Date | null | undefined): string {
    if (!value) return '—'
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, DATE_FORMAT)
}

export function toDateInputValue(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, 'yyyy-MM-dd')
}

export function toDatetimeInputValue(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? parseISO(value) : value
    return format(date, "yyyy-MM-dd'T'HH:mm")
}

export function currentMonth(): string {
    return format(new Date(), 'yyyy-MM')
}

export function shiftMonth(month: string, delta: number): string {
    return format(addMonths(parseISO(`${month}-01`), delta), 'yyyy-MM')
}

export function formatMonthLabel(month: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(parseISO(`${month}-01`))
}

export function formatDayLabel(date: string, locale: string): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(parseISO(date))
}

export function todayInputValue(): string {
    return format(new Date(), 'yyyy-MM-dd')
}
