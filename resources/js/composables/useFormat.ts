export const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
export function formatDate(date: string): string {
    const d = new Date(`${date.slice(0, 10)}T00:00:00+09:00`);
    return new Intl.DateTimeFormat('ja-JP', { month: 'long', day: 'numeric', weekday: 'short', timeZone: 'Asia/Tokyo' }).format(d);
}
export function formatMoney(value: number): string { return `¥${new Intl.NumberFormat('ja-JP').format(value)}`; }
export function addDays(date: string, count: number): string {
    const d = new Date(`${date}T12:00:00Z`); d.setUTCDate(d.getUTCDate() + count); return d.toISOString().slice(0, 10);
}
