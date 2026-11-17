export interface User { id: number; name: string; email: string; role: 'admin' | 'client' }
export interface Trainer { id: number; name: string; specialty: string; bio: string; color: string; is_active: boolean }
export interface CalendarDay { date: string; day: number; weekday: number; closed: boolean }
export type SlotStatus = 'available' | 'full' | 'closed' | 'past';
export interface Slot { status: SlotStatus; availableCount: number }
export interface Calendar { start: string; end: string; days: CalendarDay[]; times: string[]; slots: Record<string, Record<string, Slot>> }
export interface ReservationView {
    id: number; reference: string; date: string; time: string; end_time: string; starts_at: string;
    trainer: { id: number; name: string }; client: { id: number; name: string; email: string };
    status: 'confirmed' | 'cancelled'; notes: string | null; duration: number; price: number;
}
export interface TrainerScheduleView { id: number; trainer_id: number; day_of_week: number; start_time: string; end_time: string; is_working: boolean }
export interface SharedProps { auth: { user: User | null }; flash: { success?: string; error?: string }; [key: string]: unknown }
