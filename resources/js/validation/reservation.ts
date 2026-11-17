export function validateReservation(date: string, time: string, notes: string): string | null {
    if (!date || !time) return 'カレンダーからご希望の日時を選択してください。';
    if (notes.length > 1000) return 'ご要望・連絡事項は1,000文字以内で入力してください。';
    return null;
}
