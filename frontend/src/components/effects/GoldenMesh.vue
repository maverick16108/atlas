<script setup>
import { onMounted, onUnmounted, ref } from 'vue'

const canvas = ref(null)
let ctx = null
let animationFrameId = null
let particles = []
const mouse = { x: null, y: null, radius: 150 }
let windowWidth = window.innerWidth
let windowHeight = window.innerHeight

const resize = () => {
    if (canvas.value) {
        windowWidth = window.innerWidth
        windowHeight = window.innerHeight
        canvas.value.width = windowWidth
        canvas.value.height = windowHeight
        initParticles()
    }
}

const handleMouseMove = (e) => {
    mouse.x = e.x
    mouse.y = e.y
}

const handleMouseLeave = () => {
    mouse.x = undefined
    mouse.y = undefined
}

class Particle {
    constructor(x, y, directionX, directionY, size, color) {
        this.x = x
        this.y = y
        this.directionX = directionX
        this.directionY = directionY
        this.size = size
        this.color = color
    }
    
    draw() {
        if (!ctx) return
        ctx.beginPath()
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2, false)
        ctx.fillStyle = this.color
        ctx.fill()
    }
    
    update() {
        // Wall interaction
        if (this.x > canvas.value.width || this.x < 0) this.directionX = -this.directionX
        if (this.y > canvas.value.height || this.y < 0) this.directionY = -this.directionY

        // Mouse interaction
        if (mouse.x != undefined) {
            let dx = mouse.x - this.x
            let dy = mouse.y - this.y
            let distance = Math.sqrt(dx*dx + dy*dy)
            
             // Gentle repel/attract mesh
            if (distance < mouse.radius) {
                if (mouse.x < this.x && this.x < canvas.value.width - this.size * 10) this.x += 2
                if (mouse.x > this.x && this.x > this.size * 10) this.x -= 2
                if (mouse.y < this.y && this.y < canvas.value.height - this.size * 10) this.y += 2
                if (mouse.y > this.y && this.y > this.size * 10) this.y -= 2
            }
        }
        
        this.x += this.directionX
        this.y += this.directionY
        this.draw()
    }
}

function initParticles() {
    particles = []
    // Density
    let numberOfParticles = (canvas.value.width * canvas.value.height) / 15000 
    
    for (let i = 0; i < numberOfParticles; i++) {
        let size = (Math.random() * 2) + 0.1
        
        // Spawn Logic: Prefer sides
        let x, y
        if (Math.random() > 0.2) { 
             // 80% chance to spawn on sides
             if (Math.random() > 0.5) x = Math.random() * (windowWidth * 0.25) // Left 25%
             else x = windowWidth - (Math.random() * (windowWidth * 0.25)) // Right 25%
        } else {
             // 20% chance to spawn in middle (will be hidden by content mostly)
             x = Math.random() * windowWidth
        }
        
        y = Math.random() * windowHeight

        let directionX = (Math.random() * 0.4) - 0.2
        let directionY = (Math.random() * 0.4) - 0.2 // Float drift
        let color = '#D4AF37'
        
        particles.push(new Particle(x, y, directionX, directionY, size, color))
    }
}

function animate() {
    if (!canvas.value) return
    animationFrameId = requestAnimationFrame(animate)
    ctx.clearRect(0,0, canvas.value.width, canvas.value.height)
    
    for (let i = 0; i < particles.length; i++) {
        particles[i].update()
    }
    connect()
}

function connect() {
    let opacityValue = 1
    for (let a = 0; a < particles.length; a++) {
        for (let b = a; b < particles.length; b++) {
            let distance = ((particles[a].x - particles[b].x) * (particles[a].x - particles[b].x))
            + ((particles[a].y - particles[b].y) * (particles[a].y - particles[b].y))
            
            // Connection distance
            if (distance < (canvas.value.width/9) * (canvas.value.height/9)) {
                opacityValue = 1 - (distance/15000)
                if (opacityValue > 0) {
                    ctx.strokeStyle = 'rgba(212,175,55,' + (opacityValue * 0.25) + ')'
                    ctx.lineWidth = 0.5
                    ctx.beginPath()
                    ctx.moveTo(particles[a].x, particles[a].y)
                    ctx.lineTo(particles[b].x, particles[b].y)
                    ctx.stroke()
                }
            }
        }
    }
}

onMounted(() => {
    if (canvas.value) {
        ctx = canvas.value.getContext('2d')
        resize()
        window.addEventListener('resize', resize)
        window.addEventListener('mousemove', handleMouseMove)
        window.addEventListener('mouseout', handleMouseLeave)
        animate()
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', resize)
    window.removeEventListener('mousemove', handleMouseMove)
    window.removeEventListener('mouseout', handleMouseLeave)
    cancelAnimationFrame(animationFrameId)
})
</script>

<template>
  <canvas ref="canvas" class="fixed inset-0 z-0 pointer-events-none opacity-80"></canvas>
</template>
