import { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Send, Sparkles, X } from 'lucide-react';
import axios from 'axios';

interface Message {
    role: 'user' | 'model';
    content: string;
}

export default function ChatWidget() {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const [inputValue, setInputValue] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const isIdle = !isOpen;

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, isOpen]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!inputValue.trim() || isLoading) return;

        const userMessage = inputValue.trim();
        setInputValue('');
        setMessages(prev => [...prev, { role: 'user', content: userMessage }]);
        setIsLoading(true);

        try {
            const response = await axios.post('/api/chat', {
                message: userMessage,
                history: messages.map(m => ({ role: m.role, content: m.content }))
            });

            setMessages(prev => [...prev, { role: 'model', content: response.data.response }]);
        } catch (error) {
            console.error('Chat error:', error);
            setMessages(prev => [...prev, { role: 'model', content: 'Maaf, terjadi kesalahan. Silakan coba lagi.' }]);
        } finally {
            setIsLoading(false);
        }
    };

    const renderMessageContent = (content: string) =>\n        content\n            .split(/\\n{2,}/)\n            .map((paragraph, index) => (\n                <p key={index} className={`leading-relaxed ${index > 0 ? 'mt-1.5' : ''}`}>\n                    {paragraph.trim()}\n                </p>\n            ));\n\n    return (
        <div className="pointer-events-none fixed bottom-6 right-6 z-50 flex flex-col items-end">
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, scale: 0.8, y: 20 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.8, y: 20 }}
                        className="pointer-events-auto relative mb-4 flex h-[520px] w-[360px] flex-col overflow-hidden rounded-[32px] border border-white/30 bg-white/20 shadow-[0_30px_80px_rgba(16,185,129,0.35)] backdrop-blur-2xl"
                    >
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(74,222,128,0.35),_transparent_65%)]" />

                        <div className="relative z-10 flex h-full flex-col gap-4 p-4">
                            {/* Header */}
                            <div className="flex items-center justify-between rounded-[28px] border border-white/40 bg-gradient-to-r from-emerald-500 via-green-500 to-lime-400 p-4 text-white shadow-[0_10px_30px_rgba(22,163,74,0.45)]">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/25 text-base font-semibold shadow-inner shadow-emerald-200/40">
                                        AI
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.2em] text-white/70">Pemandu Virtual</p>
                                        <h3 className="text-lg font-semibold">CiptaMuri AI</h3>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setIsOpen(false)}
                                    className="rounded-full bg-white/15 p-1 transition-colors hover:bg-white/30"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Messages */}
                            <div className="flex-1 overflow-y-auto rounded-[28px] border border-white/20 bg-white/10 p-4 shadow-inner backdrop-blur-lg">
                                <div className="space-y-4">
                                    {messages.length === 0 && (
                                        <div className="mt-12 text-center text-emerald-900/60">
                                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-[20px] border border-white/40 bg-white/30 text-emerald-600 shadow-inner">
                                                <Sparkles className="h-8 w-8" />
                                            </div>
                                            <p>Halo! Saya CiptaMuri AI. Ada yang bisa saya bantu?</p>
                                        </div>
                                    )}
                                    {messages.map((msg, idx) => (
                                        <div
                                            key={idx}
                                            className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                                        >
                                            <div
                                                className={`max-w-[80%] rounded-3xl px-4 py-3 text-sm ${
                                                    msg.role === 'user'
                                                        ? 'rounded-br-md border border-white/30 bg-gradient-to-br from-emerald-500 to-green-500 text-white shadow-lg shadow-emerald-200/40'
                                                        : 'rounded-bl-md border border-white/40 bg-white/60 text-emerald-900 shadow-sm backdrop-blur-lg'
                                                }`}
                                            >
                                                {renderMessageContent(msg.content)}
                                            </div>
                                        </div>
                                    ))}
                                    {isLoading && (
                                        <div className="flex justify-start">
                                            <div className="rounded-3xl border border-white/30 bg-white/70 px-4 py-3 text-emerald-700 shadow-inner">
                                                <div className="flex gap-1">
                                                    <span className="h-2 w-2 animate-bounce rounded-full bg-emerald-400" style={{ animationDelay: '0ms' }} />
                                                    <span className="h-2 w-2 animate-bounce rounded-full bg-emerald-400" style={{ animationDelay: '120ms' }} />
                                                    <span className="h-2 w-2 animate-bounce rounded-full bg-emerald-400" style={{ animationDelay: '240ms' }} />
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                    <div ref={messagesEndRef} />
                                </div>
                            </div>

                            {/* Input */}
                            <div className="rounded-[26px] border border-white/30 bg-white/20 p-2 shadow-lg shadow-emerald-200/30">
                                <form onSubmit={handleSubmit} className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        value={inputValue}
                                        onChange={(e) => setInputValue(e.target.value)}
                                        placeholder="Tulis pesan..."
                                        className="flex-1 rounded-full border border-transparent bg-transparent px-4 py-2 text-sm text-emerald-900 placeholder:text-emerald-300 focus:border-emerald-400 focus:outline-none"
                                    />
                                    <button
                                        type="submit"
                                        disabled={isLoading || !inputValue.trim()}
                                        className="rounded-full bg-gradient-to-r from-emerald-500 via-green-500 to-lime-400 p-3 text-white shadow-[0_10px_20px_rgba(16,185,129,0.35)] transition hover:brightness-110 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <Send className="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            <motion.div
                drag
                dragConstraints={{ left: -window.innerWidth + 100, right: 0, top: -window.innerHeight + 100, bottom: 0 }}
                whileHover={{ scale: 1.1 }}
                whileTap={{ scale: 0.95 }}
                className="pointer-events-auto relative"
            >
                <AnimatePresence>
                    {isIdle && (
                        <motion.div
                            key="idle-ring"
                            initial={{ opacity: 0, scale: 0.4 }}
                            animate={{ opacity: [0, 0.3, 0], scale: [0.8, 2.5, 1.2] }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 2.8, repeat: Infinity, ease: 'easeInOut' }}
                            className="absolute inset-0 m-auto h-16 w-16 rounded-full bg-emerald-300/80 blur-3xl"
                        />
                    )}
                </AnimatePresence>
                <AnimatePresence>
                    {isIdle && (
                        <motion.div
                            key="idle-glow"
                            initial={{ opacity: 0, scale: 0.8 }}
                            animate={{ opacity: [0.2, 1, 0.2], scale: [0.9, 1.05, 0.95] }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 1.3, repeat: Infinity, repeatType: 'loop' }}
                            className="absolute -right-3 -top-3 h-6 w-6 rounded-full bg-gradient-to-r from-lime-400 to-emerald-500 shadow-lg"
                        />
                    )}
                </AnimatePresence>
                <button
                    onClick={() => setIsOpen((prev) => !prev)}
                    className="relative z-10 flex h-16 w-16 items-center justify-center rounded-[26px] border border-white/30 bg-gradient-to-br from-emerald-500 via-green-500 to-lime-400 text-white shadow-[0_20px_40px_rgba(16,185,129,0.45)] transition-shadow hover:shadow-[0_25px_50px_rgba(16,185,129,0.55)]"
                >
                    {isOpen ? (
                        <X className="h-6 w-6" />
                    ) : (
                        <div className="flex h-9 w-9 items-center justify-center rounded-2xl bg-white/20 text-lg font-semibold">
                            AI
                        </div>
                    )}
                </button>
            </motion.div>
        </div>
    );
}

