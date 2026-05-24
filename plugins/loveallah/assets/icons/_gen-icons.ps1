Add-Type -AssemblyName System.Drawing

$srcPath = 'C:\Users\user\Documents\loveallah\plugins\loveallah\assets\img\logo.png'
$dstDir  = 'C:\Users\user\Documents\loveallah\plugins\loveallah\assets\icons'
$brand   = [System.Drawing.Color]::FromArgb(255, 0xED, 0x1C, 0x6C)  # #ED1C6C

$src = [System.Drawing.Image]::FromFile($srcPath)
Write-Host "Source: $($src.Width)x$($src.Height) $($src.PixelFormat)"

function Render-Icon([int]$size, [bool]$maskable, [string]$outPath) {
	$bmp = New-Object System.Drawing.Bitmap($size, $size)
	$gfx = [System.Drawing.Graphics]::FromImage($bmp)
	$gfx.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
	$gfx.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
	$gfx.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality

	# Pink background
	$gfx.Clear($brand)

	# Logo target ratio: 70% of canvas for normal icons (some margin),
	# 60% for maskable (Android safe zone needs the outer 10% inert).
	$ratio = if ($maskable) { 0.60 } else { 0.78 }
	$boxW = [int]($size * $ratio)
	$boxH = [int]($size * $ratio)

	# Preserve aspect ratio — fit the logo inside the box
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
	$y = [int](($size - $drawH) / 2)

	$gfx.DrawImage($src, $x, $y, $drawW, $drawH)
	$bmp.Save($outPath, [System.Drawing.Imaging.ImageFormat]::Png)
	$gfx.Dispose()
	$bmp.Dispose()
	Write-Host "  → $($outPath | Split-Path -Leaf) ($size px, maskable=$maskable)"
}

# Standard icons (pink background, logo at ~78% of canvas)
@(48, 72, 96, 128, 144, 152, 192, 384, 512) | ForEach-Object {
	Render-Icon -size $_ -maskable $false -outPath (Join-Path $dstDir "icon-$_.png")
}

# Maskable variants — 20% padding for Android adaptive icon safe zone
Render-Icon -size 192 -maskable $true -outPath (Join-Path $dstDir 'icon-192-maskable.png')
Render-Icon -size 512 -maskable $true -outPath (Join-Path $dstDir 'icon-512-maskable.png')

# Favicons — small composited variants
Render-Icon -size 16  -maskable $false -outPath (Join-Path $dstDir 'favicon-16.png')
Render-Icon -size 32  -maskable $false -outPath (Join-Path $dstDir 'favicon-32.png')
Render-Icon -size 180 -maskable $false -outPath (Join-Path $dstDir 'apple-touch-icon-180.png')

$src.Dispose()
Write-Host ""
Write-Host "Done. Generated:"
Get-ChildItem $dstDir -Filter "*.png" | Sort-Object Name | ForEach-Object {
	"  $($_.Name)  $($_.Length) bytes"
}
