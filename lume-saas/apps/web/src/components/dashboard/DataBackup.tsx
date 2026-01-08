"use client";

import { useState, useRef } from "react";
import { Download, Upload, Loader2, Check, X } from "lucide-react";

export function DataBackup() {
    const [exporting, setExporting] = useState(false);
    const [importing, setImporting] = useState(false);
    const [result, setResult] = useState<{ type: 'success' | 'error', message: string } | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleExport = async () => {
        setExporting(true);
        setResult(null);
        try {
            const response = await fetch('/api/data/export');
            if (!response.ok) throw new Error('Erro ao exportar');

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `lume-backup-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();

            setResult({ type: 'success', message: 'Backup baixado!' });
        } catch (error: any) {
            setResult({ type: 'error', message: error.message });
        } finally {
            setExporting(false);
            setTimeout(() => setResult(null), 3000);
        }
    };

    const handleImport = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setImporting(true);
        setResult(null);

        try {
            const text = await file.text();
            const data = JSON.parse(text);

            const response = await fetch('/api/data/import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (!response.ok) throw new Error(result.error);

            setResult({ type: 'success', message: result.message });

            // Refresh page to show new data
            setTimeout(() => window.location.reload(), 1500);

        } catch (error: any) {
            setResult({ type: 'error', message: error.message });
        } finally {
            setImporting(false);
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    };

    return (
        <div className="flex items-center gap-3">
            {result && (
                <div className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ${result.type === 'success'
                        ? 'bg-emerald-50 text-emerald-600'
                        : 'bg-red-50 text-red-600'
                    }`}>
                    {result.type === 'success' ? <Check className="w-3 h-3" /> : <X className="w-3 h-3" />}
                    {result.message}
                </div>
            )}

            <button
                onClick={handleExport}
                disabled={exporting || importing}
                className="flex items-center gap-2 px-3 py-2 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-gray-50 hover:border-gray-300 transition-all disabled:opacity-50"
                title="Baixar backup dos dados"
            >
                {exporting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
                Backup
            </button>

            <label className="flex items-center gap-2 px-3 py-2 text-xs font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-gray-50 hover:border-gray-300 transition-all cursor-pointer">
                {importing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Upload className="w-4 h-4" />}
                Restaurar
                <input
                    ref={fileInputRef}
                    type="file"
                    accept=".json"
                    onChange={handleImport}
                    disabled={importing || exporting}
                    className="hidden"
                />
            </label>
        </div>
    );
}
