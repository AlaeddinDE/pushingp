#!/bin/bash
# Create placeholder images for Apple Wallet

# Icon (29x29, 58x58, 87x87)
convert -size 29x29 xc:'#8b5cf6' -fill white -gravity center \
  -pointsize 20 -annotate +0+0 'P' icon.png

convert -size 58x58 xc:'#8b5cf6' -fill white -gravity center \
  -pointsize 40 -annotate +0+0 'P' icon@2x.png

convert -size 87x87 xc:'#8b5cf6' -fill white -gravity center \
  -pointsize 60 -annotate +0+0 'P' icon@3x.png

# Logo (160x50, 320x100, 480x150)
convert -size 160x50 xc:'none' -fill white -gravity center \
  -pointsize 24 -annotate +0+0 'PUSHING P' logo.png

convert -size 320x100 xc:'none' -fill white -gravity center \
  -pointsize 48 -annotate +0+0 'PUSHING P' logo@2x.png

convert -size 480x150 xc:'none' -fill white -gravity center \
  -pointsize 72 -annotate +0+0 'PUSHING P' logo@3x.png

# Strip (375x123, 750x246, 1125x369)
convert -size 375x123 gradient:'#8b5cf6-#7c3aed' strip.png
convert -size 750x246 gradient:'#8b5cf6-#7c3aed' strip@2x.png
convert -size 1125x369 gradient:'#8b5cf6-#7c3aed' strip@3x.png

echo "✅ Placeholder images created"
