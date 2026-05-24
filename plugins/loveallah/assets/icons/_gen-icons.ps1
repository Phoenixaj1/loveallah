Add-Type -AssemblyName System.Drawing

# ─────────────────────────────────────────────────────────────────
# Love Allah icon generator (Wave 57)
#
# Produces a polished icon with:
#   - Diagonal pink gradient (#FF4D8F → #C90D5A) for depth
#   - Soft white halo behind the heart so it lifts off the bg
#   - Subtle inner shadow at the corners (vignette) for richness
#   - Logo composited with a slight drop shadow
#
# Two variants per icon: standard (heart ~78% of canvas) and
# maskable (heart ~58% — Android adaptive icons crop the outer 20%
# to a circle, so anything outside the inner 80% is unsafe).
# ─────────────────────────────────────────────────────────────────

$srcPath = 'C:\Users\user\Documents\loveallah\plugins\loveallah\assets\img\logo.png'
$dstDir  = 'C:\Users\user\Documents\loveallah\plugins\loveallah\assets\icons'

# Brand colours — top-left highlight to bottom-right shadow
$brandTopLeft     = [System.Drawing.Color]::FromArgb(255, 0xFF, 0x4D, 0x8F)
$brandBottomRight = [System.Drawing.Color]::FromArgb(255, 0xC9, 0x0D, 0x5A)

$src = [System.Drawing.Image]::FromFile($srcPath)
Write-Host "Source: $($src.Width)x$($src.Height) $($src.PixelFormat)"

function Render-Icon([int]$size, [bool]$maskable, [string]$outPath) {
	$bmp = New-Object System.Drawing.Bitmap($size, $size)
	$gfx = [System.Drawing.Graphics]::FromImage($bmp)
	$gfx.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
	$gfx.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
	$gfx.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
	$gfx.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality

	# ─── Background: diagonal gradient ──────────────────────────
	# LinearGradientMode::ForwardDiagonal = top-left → bottom-right
	$rectBg  = New-Object System.Drawing.Rectangle 0, 0, $size, $size
	$gradBg  = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
		$rectBg,
		$brandTopLeft,
		$brandBottomRight,
		[System.Drawing.Drawing2D.LinearGradientMode]::ForwardDiagonal
	)
	$gfx.FillRectangle($gradBg, $rectBg)
	$gradBg.Dispose()

	# ─── Vignette: subtle dark halo in the corners ──────────────
	# Drawn as a path-gradient ellipse centered on the canvas. The
	# centre is fully transparent, the outer rim is dark @ ~12%.
	$ellipsePath = New-Object System.Drawing.Drawing2D.GraphicsPath
	$ellipseSize = [int]($size * 1.45)
	$ellipseRect = New-Object System.Drawing.Rectangle (
		[int](($size - $ellipseSize) / 2)),
		([int](($size - $ellipseSize) / 2)),
		$ellipseSize,
		$ellipseSize
	$ellipsePath.AddEllipse($ellipseRect)
	$pgVig = New-Object System.Drawing.Drawing2D.PathGradientBrush($ellipsePath)
	$pgVig.CenterColor = [System.Drawing.Color]::FromArgb(0, 0, 0, 0)
	$pgVig.SurroundColors = @( [System.Drawing.Color]::FromArgb(70, 0, 0, 0) )
	$gfx.FillRectangle($pgVig, $rectBg)
	$pgVig.Dispose()
	$ellipsePath.Dispose()

	# ─── Soft white halo behind the heart ───────────────────────
	# Same path-gradient trick, but centred and small — white at the
	# core, transparent at the rim. Gives the heart that "lit from
	# within" quality you see on premium app icons.
	$haloDiameter = [int]($size * 0.78)
	$haloPath = New-Object System.Drawing.Drawing2D.GraphicsPath
	$haloRect = New-Object System.Drawing.Rectangle (
		[int](($size - $haloDiameter) / 2)),
		([int](($size - $haloDiameter) / 2)),
		$haloDiameter,
		$haloDiameter
	$haloPath.AddEllipse($haloRect)
	$pgHalo = New-Object System.Drawing.Drawing2D.PathGradientBrush($haloPath)
	$pgHalo.CenterColor = [System.Drawing.Color]::FromArgb(85, 255, 255, 255)
	$pgHalo.SurroundColors = @( [System.Drawing.Color]::FromArgb(0, 255, 255, 255) )
	$gfx.FillEllipse($pgHalo, $haloRect)
	$pgHalo.Dispose()
	$haloPath.Dispose()

	# ─── Logo target box ────────────────────────────────────────
	# Maskable variant inset 21% per side so the heart lives well
	# inside the Android adaptive-icon safe zone. Standard variant
	# inset ~11% so it fills the icon nicely.
	$ratio = if ($maskable) { 0.58 } else { 0.78 }
	$boxW = [int]($size * $ratio)
	$boxH = [int]($size * $ratio)

	$srcRatio = $src.Width / $src.Height
	$dstRatio = $boxW / $boxH
	if ($srcRatio -gt $dstRatio) {
		$drawW = $boxW
		$drawH = [int]($boxW / $srcRatio)
	} else {
		$drawH = $boxH
		$drawW = [int]($boxH * $srcRatio)
	}
	$x = [int](($size - $drawW) / 2)
	# Nudge the heart up slightly so the visual centre is balanced
	# (heart shape is bottom-heavy, so the geometric centre sits
	# below the optical centre).
	$y = [int](($size - $drawH) / 2 - $size * 0.015)

	# ─── Drop shadow under the heart (cheap blur via downsample) ─
	# Skip on very small sizes — the shadow disappears anyway and
	# the extra work blurs the logo.
	if ($size -ge 96) {
		$shadowSize = [int]($drawW * 1.05)
		$shadowBmp  = New-Object System.Drawing.Bitmap($shadowSize, $shadowSize)
		$shadowGfx  = [System.Drawing.Graphics]::FromImage($shadowBmp)
		$shadowGfx.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
		# Draw a darkened/feathered silhouette of the logo
		$cm  = New-Object System.Drawing.Imaging.ColorMatrix
		$cm.Matrix00 = 0; $cm.Matrix11 = 0; $cm.Matrix22 = 0  # zero RGB
		$cm.Matrix33 = 0.32                                   # alpha → 32%
		$ia = New-Object System.Drawing.Imaging.ImageAttributes
		$ia.SetColorMatrix($cm)
		$rect = New-Object System.Drawing.Rectangle 0, 0, $shadowSize, $shadowSize
		$shadowGfx.DrawImage(
			$src, $rect, 0, 0, $src.Width, $src.Height,
			[System.Drawing.GraphicsUnit]::Pixel, $ia
		)
		$shadowGfx.Dispose()
		$ia.Dispose()

		# Composite the shadow below + offset (cheap blur by drawing
		# at slightly larger size with bicubic interpolation).
		$shadowX = $x - [int](($shadowSize - $drawW) / 2)
		$shadowY = $y + [int]($size * 0.018) - [int](($shadowSize - $drawH) / 2)
		$gfx.DrawImage($shadowBmp, $shadowX, $shadowY, $shadowSize, $shadowSize)
		$shadowBmp.Dispose()
	}

	# ─── Logo on top ────────────────────────────────────────────
	$gfx.DrawImage($src, $x, $y, $drawW, $drawH)

	$bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
	$gfx.Dispose()
	$bmp.Dispose()
	Write-Host "  → $($outPath | Split-Path -Leaf) ($size px, maskable=$maskable)"
}

# Standard icons (heart at ~78% of canvas — fills nicely)
@(48, 72, 96, 128, 144, 152, 192, 384, 512) | ForEach-Object {
	Render-Icon -size $_ -maskable $false -outPath (Join-Path $dstDir "icon-$_.png")
}

# Maskable variants — heart at ~58% to stay inside the circular safe zone
Render-Icon -size 192 -maskable $true -outPath (Join-Path $dstDir 'icon-192-maskable.png')
Render-Icon -size 512 -maskable $true -outPath (Join-Path $dstDir 'icon-512-maskable.png')

# Favicons + Apple touch
Render-Icon -size 16  -maskable $false -outPath (Join-Path $dstDir 'favicon-16.png')
Render-Icon -size 32  -maskable $false -outPath (Join-Path $dstDir 'favicon-32.png')
Render-Icon -size 180 -maskable $false -outPath (Join-Path $dstDir 'apple-touch-icon-180.png')

$src.Dispose()
Write-Host ""
Write-Host "Done. Generated:"
Get-ChildItem $dstDir -Filter "*.png" | Sort-Object Name | ForEach-Object {
	"  $($_.Name)  $($_.Length) bytes"
}
