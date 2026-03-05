import { createClient } from '@/lib/supabase/server';
import { NextResponse } from 'next/server';

export async function GET(request: Request) {
    try {
        const { searchParams } = new URL(request.url);
        const startDate = searchParams.get('startDate');
        const endDate = searchParams.get('endDate');

        const supabase = await createClient();
        const { data: { user } } = await supabase.auth.getUser();

        if (!user) {
            return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
        }

        let query = supabase
            .from('calendar_events')
            .select('*')
            .eq('user_id', user.id);

        if (startDate) {
            query = query.gte('date', startDate);
        }
        if (endDate) {
            query = query.lte('date', endDate);
        }

        const { data, error } = await query;

        if (error) {
            console.error('Error fetching calendar events:', error);
            return NextResponse.json({ error: 'Failed to fetch events' }, { status: 500 });
        }

        return NextResponse.json(data);
    } catch (error) {
        console.error('Internal Error:', error);
        return NextResponse.json({ error: 'Internal Server Error' }, { status: 500 });
    }
}

export async function POST(request: Request) {
    try {
        const supabase = await createClient();
        const { data: { user } } = await supabase.auth.getUser();

        if (!user) {
            return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
        }

        const body = await request.json();
        const { date, type } = body;

        if (!date) {
            return NextResponse.json({ error: 'Date is required' }, { status: 400 });
        }

        // If type is null or specific value to "remove", we delete.
        // However, the interface usually toggles.
        // Let's assume sending "type" as null or not present means delete?
        // Or we stick to the plan: None -> Work -> ...
        // If the user sends a type, we upsert. If they want to clear, they can send a DELETE request or a specific type (e.g. null).
        // Let's handle explicit upsert here.

        if (!type) {
            // If no type provided, assume deletion for that date
            const { error } = await supabase
                .from('calendar_events')
                .delete()
                .eq('user_id', user.id)
                .eq('date', date);

            if (error) throw error;
            return NextResponse.json({ success: true, deleted: true });
        }

        // Upsert
        const { data, error } = await supabase
            .from('calendar_events')
            .upsert({
                user_id: user.id,
                date: date,
                type: type,
                updated_at: new Date().toISOString()
            }, {
                onConflict: 'user_id,date'
            })
            .select()
            .single();

        if (error) {
            console.error('Error saving calendar event:', error);
            return NextResponse.json({ error: 'Failed to save event' }, { status: 500 });
        }

        return NextResponse.json(data);
    } catch (error) {
        console.error('Internal Error:', error);
        return NextResponse.json({ error: 'Internal Server Error' }, { status: 500 });
    }
}
