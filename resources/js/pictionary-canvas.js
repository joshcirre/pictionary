const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

const distanceBetween = (a, b) => Math.hypot((b?.x ?? 0) - (a?.x ?? 0), (b?.y ?? 0) - (a?.y ?? 0));

const simplifyPoints = (points, minDist = 0.004) => {
    if (!Array.isArray(points) || points.length <= 2) return points ?? [];
    const out = [points[0]];
    for (let i = 1; i < points.length - 1; i++) {
        if (distanceBetween(out[out.length - 1], points[i]) >= minDist) out.push(points[i]);
    }
    const last = points[points.length - 1];
    if (distanceBetween(out[out.length - 1], last) > 0) out.push(last);
    return out;
};

const mergePoints = (existing, incoming) => {
    if (!existing?.length) return incoming ?? [];
    const merged = [...existing];
    let last = merged[merged.length - 1];
    for (const p of incoming ?? []) {
        if (p.x === last.x && p.y === last.y) continue;
        merged.push(p);
        last = p;
    }
    return merged;
};

const strokePath = (ctx, canvas, points) => {
    const scaled = points.map((p) => ({ x: p.x * canvas.width, y: p.y * canvas.height }));
    ctx.beginPath();
    ctx.moveTo(scaled[0].x, scaled[0].y);
    if (scaled.length === 2) {
        ctx.lineTo(scaled[1].x, scaled[1].y);
        return;
    }
    for (let i = 1; i < scaled.length - 1; i++) {
        const mid = { x: (scaled[i].x + scaled[i + 1].x) / 2, y: (scaled[i].y + scaled[i + 1].y) / 2 };
        ctx.quadraticCurveTo(scaled[i].x, scaled[i].y, mid.x, mid.y);
    }
    const pen = scaled[scaled.length - 2],
        lst = scaled[scaled.length - 1];
    ctx.quadraticCurveTo(pen.x, pen.y, lst.x, lst.y);
};

const drawStroke = (ctx, canvas, stroke, color = '#1e1e1e') => {
    if (!stroke.points?.length) return;
    const lw = Math.max(3, Math.min(canvas.width, canvas.height) * (stroke.width_ratio ?? 0.012));
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = lw;
    ctx.strokeStyle = color;
    if (stroke.points.length === 1) {
        const p = stroke.points[0];
        ctx.beginPath();
        ctx.arc(p.x * canvas.width, p.y * canvas.height, lw / 2, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
    } else {
        strokePath(ctx, canvas, stroke.points);
        ctx.stroke();
    }
    ctx.restore();
};

const resizeCanvas = (canvas) => {
    if (!canvas) return;
    const r = window.devicePixelRatio ?? 1;
    const w = Math.floor(canvas.clientWidth * r);
    const h = Math.floor(canvas.clientHeight * r);
    if (canvas.width !== w || canvas.height !== h) {
        canvas.width = w;
        canvas.height = h;
    }
};

window.pictionaryRoom = (roomCode, isDrawer, myWord) => ({
    roomCode,
    isDrawer,
    myWord,

    strokes: {},
    drawing: false,
    currentStrokeId: null,
    currentPoints: [],
    lastSyncCount: 0,
    lastSyncAt: 0,
    activePointerId: null,
    resizeObserver: null,
    channel: null,

    overlayVisible: false,
    overlayStatus: '',
    overlayWord: '',
    overlayWinnerName: '',
    overlayTimer: null,

    init() {
        this.resizeObserver = new ResizeObserver(() => this.onResize());
        if (this.$refs.displayCanvas) this.resizeObserver.observe(this.$refs.displayCanvas);

        this.onResize();

        const pusher = window.Echo?.connector?.pusher;
        if (pusher) {
            this.channel = pusher.subscribe(`room.${this.roomCode}`);
            this.channel.bind('App\\Events\\StrokeSynced', (data) => {
                this.applyRemoteStroke(data.stroke);
            });
            // Handle overlay directly via Pusher — avoids Livewire batching both
            // RoundEnded + RoundStarted Echo requests and losing the dispatch
            this.channel.bind('App\\Events\\RoundEnded', (data) => {
                this.overlayStatus = data.status ?? '';
                this.overlayWord = data.word ?? '';
                this.overlayWinnerName = data.winner_name ?? '';
                this.overlayVisible = true;
                clearTimeout(this.overlayTimer);
            });
            this.channel.bind('App\\Events\\RoundStarted', () => {
                clearTimeout(this.overlayTimer);
                this.overlayTimer = setTimeout(() => {
                    this.overlayVisible = false;
                }, 3000);
            });
        }

        // For the correct guesser: excluded from Pusher broadcasts, so submitGuess
        // dispatches these events locally via Livewire
        window.addEventListener('round-ended', (e) => {
            this.overlayStatus = e.detail.status ?? '';
            this.overlayWord = e.detail.word ?? '';
            this.overlayWinnerName = e.detail.winner_name ?? '';
            this.overlayVisible = true;
            clearTimeout(this.overlayTimer);
        });

        window.addEventListener('timer-reset', () => {
            this.clearCanvas();
            this.resetTimer();
            clearTimeout(this.overlayTimer);
            this.overlayTimer = setTimeout(() => {
                this.overlayVisible = false;
            }, 3000);
        });

        // Replay persisted strokes for late-joiners and refreshers
        window.axios
            .get(`/rooms/${this.roomCode}/strokes`)
            .then(({ data }) => {
                const strokes = data.strokes ?? {};
                for (const stroke of Object.values(strokes)) {
                    if (stroke?.id) this.strokes[stroke.id] = stroke;
                }
                this.redraw();
            })
            .catch(() => {});

        this.startTimer();
        this.renderLoop();
    },

    destroy() {
        this.resizeObserver?.disconnect();
        this.channel?.unbind_all();
        window.Echo?.connector?.pusher?.unsubscribe(`room.${this.roomCode}`);
    },

    onResize() {
        resizeCanvas(this.$refs.displayCanvas);
        resizeCanvas(this.$refs.drawCanvas);
        this.redraw();
    },

    clearCanvas() {
        this.strokes = {};
        const canvas = this.$refs.displayCanvas;
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        const dc = this.$refs.drawCanvas;
        if (dc) {
            const ctx = dc.getContext('2d');
            ctx.clearRect(0, 0, dc.width, dc.height);
        }
    },

    redraw() {
        const canvas = this.$refs.displayCanvas;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const stroke of Object.values(this.strokes)) {
            drawStroke(ctx, canvas, stroke);
        }
    },

    renderLoop() {
        if (this.$refs.drawCanvas && this.currentPoints.length) {
            const canvas = this.$refs.drawCanvas;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawStroke(ctx, canvas, { id: 'preview', points: this.currentPoints, width_ratio: 0.012 });
        }
        requestAnimationFrame(() => this.renderLoop());
    },

    applyRemoteStroke(stroke) {
        if (!stroke?.id) return;
        const existing = this.strokes[stroke.id];
        if (!existing) {
            this.strokes[stroke.id] = { ...stroke };
        } else {
            existing.points = stroke.incremental ? mergePoints(existing.points, stroke.points) : stroke.points;
        }
        const canvas = this.$refs.displayCanvas;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (stroke.incremental && existing) {
            drawStroke(ctx, canvas, this.strokes[stroke.id]);
        } else {
            this.redraw();
        }
    },

    startStroke(event) {
        if (!this.$wire.isDrawer) return;
        event.preventDefault();
        this.activePointerId = event.pointerId;
        this.currentStrokeId = crypto.randomUUID();
        this.currentPoints = [this.pointFromEvent(event)];
        this.lastSyncCount = 0;
        this.lastSyncAt = 0;
        this.drawing = true;
        this.$refs.drawCanvas?.setPointerCapture(event.pointerId);
        this.syncStroke(true);
    },

    continueStroke(event) {
        if (!this.drawing || event.pointerId !== this.activePointerId) return;
        event.preventDefault();
        const p = this.pointFromEvent(event);
        const last = this.currentPoints[this.currentPoints.length - 1];
        if (!last || distanceBetween(last, p) > 0.004) {
            this.currentPoints.push(p);
            this.syncStroke();
        }
    },

    endStroke(event) {
        if (!this.drawing || event.pointerId !== this.activePointerId) return;
        event.preventDefault();
        const p = this.pointFromEvent(event);
        const last = this.currentPoints[this.currentPoints.length - 1];
        if (!last || distanceBetween(last, p) > 0.001) this.currentPoints.push(p);
        this.syncStroke(true);
        this.drawing = false;
        this.currentPoints = [];
        this.releasePointer(event.pointerId);
    },

    cancelStroke(event) {
        if (event.pointerId !== this.activePointerId) return;
        this.drawing = false;
        this.currentPoints = [];
        this.releasePointer(event.pointerId);
    },

    syncStroke(force = false) {
        if (!this.drawing || !this.currentStrokeId) return;
        const now = Date.now();
        const simplified = simplifyPoints(this.currentPoints);
        const newPoints = simplified.slice(this.lastSyncCount);
        if (!force && newPoints.length <= 2 && now - this.lastSyncAt < 80) return;
        if (!newPoints.length) return;

        const payload = {
            id: this.currentStrokeId,
            points: newPoints,
            width_ratio: 0.012,
            incremental: true,
        };

        // Drawer applies their own strokes locally (excluded from Pusher broadcast)
        this.applyRemoteStroke(payload);

        window.axios
            .post(`/rooms/${this.roomCode}/strokes`, payload, {
                headers: window.Echo?.socketId() ? { 'X-Socket-ID': window.Echo.socketId() } : {},
            })
            .catch(() => {});

        this.lastSyncAt = now;
        this.lastSyncCount = simplified.length;
    },

    releasePointer(pointerId) {
        try {
            this.$refs.drawCanvas?.releasePointerCapture(pointerId);
        } catch {}
        this.activePointerId = null;
    },

    pointFromEvent(event) {
        const rect = this.$refs.drawCanvas.getBoundingClientRect();
        return {
            x: clamp((event.clientX - rect.left) / rect.width, 0, 1),
            y: clamp((event.clientY - rect.top) / rect.height, 0, 1),
        };
    },

    // Timer — reads $wire.roundEndsAt (wyrd pattern)
    secondsLeft: 30,
    timerInterval: null,

    startTimer() {
        this.tickTimer();
        this.timerInterval = setInterval(() => this.tickTimer(), 1000);
    },

    tickTimer() {
        const endsAt = this.$wire.roundEndsAt;
        if (!endsAt) {
            this.secondsLeft = 30;
            return;
        }
        const diff = Math.floor((new Date(endsAt) - Date.now()) / 1000);
        this.secondsLeft = Math.max(0, diff);
    },

    resetTimer() {
        clearInterval(this.timerInterval);
        this.timerInterval = setInterval(() => this.tickTimer(), 1000);
        this.tickTimer();
    },
});
