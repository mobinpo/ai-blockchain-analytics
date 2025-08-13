# 🎬 AI Blockchain Analytics - Video Production Guide

## 🚀 Quick Start

### Generate Video Production System
```bash
php artisan video:promo --generate-assets --create-scenes --auto-record
```

### Recording Commands
```bash
# Full automated setup
php artisan video:promo --generate-assets --create-scenes

# Custom duration and resolution
php artisan video:promo --duration=120 --resolution=1920x1080 --fps=30

# Generate only assets
php artisan video:promo --generate-assets
```

## 📁 Output Structure

```
video-output/
├── scenes/                     # HTML templates for web scenes
│   ├── headlines.html         # DeFi exploit headlines
│   ├── statistics.html        # Performance metrics
│   ├── contact.html          # Call to action
│   └── *-template.md         # Recording instructions
├── assets/                    # Visual assets and configuration
│   ├── demo-data.json        # Demo contract data
│   └── visual-config.json    # Colors, fonts, animations
├── scripts/                   # Recording and editing scripts
│   ├── record-promo.sh       # Main recording script
│   ├── record-*.sh           # Individual scene scripts
│   ├── edit-video.sh         # Post-production pipeline
│   └── export-final.sh       # Multi-format export
├── raw-footage/               # Recorded scene files
├── final/                     # Finished video files
└── recording-config.json      # Technical specifications
```

## 🎥 Recording Workflow

### 1. Pre-Production Setup
```bash
# Generate all assets and templates
php artisan video:promo --generate-assets --create-scenes

# Review scene templates
ls video-output/scenes/*-template.md
```

### 2. Recording Environment Setup
- **Browser**: Full-screen mode, clean setup
- **Platform**: Demo environment running
- **Audio**: Professional microphone setup
- **Recording Software**: OBS Studio or similar
- **Resolution**: 1920x1080 @ 30fps

### 3. Scene-by-Scene Recording
```bash
# Record all scenes in sequence
./video-output/scripts/record-promo.sh

# Or record individual scenes
./video-output/scripts/record-hook.sh
./video-output/scripts/record-solution.sh
# ... etc
```

### 4. Post-Production
```bash
# Combine and edit scenes
./video-output/scripts/edit-video.sh

# Export final formats
./video-output/scripts/export-final.sh
```

## 🎬 Scene Breakdown (120 seconds total)

| Scene | Duration | Start | Description | Key Elements |
|-------|----------|-------|-------------|--------------|
| **Hook** | 10s | 0s | DeFi exploit headlines | Euler Finance, Multichain Bridge |
| **Solution** | 20s | 10s | Platform introduction | Dashboard, logo, features |
| **Analysis Demo** | 15s | 30s | Contract analysis live | Address input, risk score |
| **Sentiment Demo** | 15s | 45s | Social sentiment monitoring | Twitter, Reddit, Telegram |
| **Reporting Demo** | 15s | 60s | PDF generation | Professional reports |
| **Famous Contracts** | 15s | 75s | Historical analysis | Exploit database |
| **Proof Points** | 20s | 90s | Statistics & credibility | 500 analyses, 99.9% uptime |
| **Call to Action** | 10s | 110s | Contact & next steps | Website, GitHub, contact |

## 🎨 Visual Assets

### Color Palette
- **Primary**: #4ecdc4 (Teal)
- **Secondary**: #44a08d (Green)
- **Danger**: #ff4757 (Red)
- **Warning**: #ffa726 (Orange)
- **Success**: #26de81 (Green)
- **Background**: #2c3e50 (Dark Blue)

### Typography
- **Headings**: Arial, bold, large sizes
- **Body**: Helvetica, readable
- **Code**: Monaco, monospace

### Animations
- **Fade In/Out**: 0.5s duration
- **Slide Transitions**: 0.8s duration
- **Pulse Effects**: 2s infinite

## 📊 Demo Data

### Featured Contracts
1. **Uniswap V2 Factory** - Low Risk (Score: 25)
2. **Euler Finance** - Critical Risk (Score: 95)
3. **Compound ETH** - Medium Risk (Score: 40)

### Sentiment Data
- **Twitter**: 65% positive, 1,250 mentions
- **Reddit**: 45% positive, 890 mentions  
- **Telegram**: 72% positive, 2,100 mentions

## 🎤 Audio Requirements

### Voiceover Specifications
- **Quality**: Studio-grade recording
- **Tone**: Professional, confident, clear
- **Pace**: 150-160 words per minute
- **Format**: WAV 48kHz stereo

### Music Track
- **Style**: Corporate/Tech, uplifting
- **Duration**: Exactly 120 seconds
- **Volume**: Background level (-20dB)
- **Fade**: In/out transitions

## 📤 Export Formats

### Primary Outputs
- **High Quality**: 1920x1080 MP4 (YouTube/Web)
- **Mobile Optimized**: 1280x720 MP4
- **Social Media**: 60-second version
- **Preview GIF**: First 10 seconds

### Technical Specs
- **Video Codec**: H.264
- **Audio Codec**: AAC 192kbps
- **Bitrate**: 10-15 Mbps
- **Frame Rate**: 30fps

## 🔧 Required Software

### Recording
- **OBS Studio** (Free, cross-platform)
- **FFmpeg** (Command-line processing)
- **Browser** (Chrome/Firefox recommended)

### Audio
- **Audacity** (Free audio editing)
- **Professional microphone**
- **Audio interface** (optional)

### Optional
- **DaVinci Resolve** (Professional editing)
- **Adobe Premiere Pro** (Industry standard)
- **Final Cut Pro** (Mac only)

## 🎯 Success Metrics

### Video Performance KPIs
- **View Duration**: >80% completion rate
- **Engagement**: High like/share ratio
- **Click-through**: Traffic to platform
- **Conversion**: New user sign-ups

### Quality Checklist
- [ ] Clear, professional audio
- [ ] Smooth screen recordings
- [ ] Proper timing and pacing
- [ ] Brand consistency
- [ ] Call-to-action clarity
- [ ] Multiple format exports

## 🚀 Distribution Strategy

### Primary Platforms
- **YouTube**: Main hosting platform
- **LinkedIn**: Professional audience
- **Twitter**: Tech community
- **GitHub**: Developer showcase

### Secondary Platforms
- **Website**: Embedded on landing page
- **Email**: Marketing campaigns
- **Presentations**: Sales demos
- **Documentation**: Feature showcase

## 📞 Production Support

### Quick Commands
```bash
# Generate complete video system
php artisan video:promo --generate-assets --create-scenes

# Check recording configuration
cat video-output/recording-config.json

# Review scene templates
ls video-output/scenes/*.md

# Start recording workflow
./video-output/scripts/record-promo.sh
```

### Troubleshooting
- **Audio Issues**: Check microphone permissions
- **Video Quality**: Verify resolution settings
- **File Size**: Adjust bitrate settings
- **Sync Issues**: Record audio separately if needed

---

**Total Production Time**: 4-6 hours  
**Target Audience**: Developers, Security Analysts, DeFi Teams  
**Primary Goal**: Drive platform adoption and enterprise interest
