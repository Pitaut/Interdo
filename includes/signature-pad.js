/**
 * Signature Pad - Simple canvas signature capture
 * Version simplifiée pour Agenda Interdo
 */

class SignaturePad {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.options = {
            backgroundColor: options.backgroundColor || 'rgba(255,255,255,0)',
            penColor: options.penColor || 'black',
            minWidth: options.minWidth || 0.5,
            maxWidth: options.maxWidth || 2.5,
            ...options
        };
        
        this.drawing = false;
        this.isEmpty = true;
        this.points = [];
        
        // Resize canvas
        this.resizeCanvas();
        
        // Setup event listeners
        this.setupEventListeners();
    }
    
    resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        this.canvas.width = this.canvas.offsetWidth * ratio;
        this.canvas.height = this.canvas.offsetHeight * ratio;
        this.canvas.getContext('2d').scale(ratio, ratio);
        this.clear();
    }
    
    setupEventListeners() {
        // Mouse events
        this.canvas.addEventListener('mousedown', this.handleMouseDown.bind(this));
        this.canvas.addEventListener('mousemove', this.handleMouseMove.bind(this));
        this.canvas.addEventListener('mouseup', this.handleMouseUp.bind(this));
        
        // Touch events
        this.canvas.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.canvas.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.canvas.addEventListener('touchend', this.handleTouchEnd.bind(this));
    }
    
    handleMouseDown(e) {
        this.drawing = true;
        this.isEmpty = false;
        const point = this.getPoint(e);
        this.points.push(point);
        this.drawPoint(point);
    }
    
    handleMouseMove(e) {
        if (!this.drawing) return;
        e.preventDefault();
        const point = this.getPoint(e);
        this.points.push(point);
        this.drawLine(this.points[this.points.length - 2], point);
    }
    
    handleMouseUp(e) {
        this.drawing = false;
        this.points = [];
    }
    
    handleTouchStart(e) {
        e.preventDefault();
        const touch = e.touches[0];
        this.handleMouseDown(touch);
    }
    
    handleTouchMove(e) {
        e.preventDefault();
        const touch = e.touches[0];
        this.handleMouseMove(touch);
    }
    
    handleTouchEnd(e) {
        this.handleMouseUp(e);
    }
    
    getPoint(e) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }
    
    drawPoint(point) {
        this.ctx.beginPath();
        this.ctx.fillStyle = this.options.penColor;
        this.ctx.arc(point.x, point.y, this.options.minWidth, 0, 2 * Math.PI);
        this.ctx.fill();
    }
    
    drawLine(from, to) {
        this.ctx.beginPath();
        this.ctx.moveTo(from.x, from.y);
        this.ctx.lineTo(to.x, to.y);
        this.ctx.strokeStyle = this.options.penColor;
        this.ctx.lineWidth = this.options.maxWidth;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.stroke();
    }
    
    clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        if (this.options.backgroundColor) {
            this.ctx.fillStyle = this.options.backgroundColor;
            this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        }
        this.isEmpty = true;
        this.points = [];
    }
    
    toDataURL(type = 'image/png', quality = 1) {
        return this.canvas.toDataURL(type, quality);
    }
    
    fromDataURL(dataURL) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                this.clear();
                this.ctx.drawImage(img, 0, 0);
                this.isEmpty = false;
                resolve();
            };
            img.onerror = reject;
            img.src = dataURL;
        });
    }
}

// Export pour utilisation
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SignaturePad;
}
