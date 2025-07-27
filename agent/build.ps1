# PowerShell 构建脚本 - 支持Windows和Linux交叉编译

param(
    [string]$Version = "1.0.0",
    [string]$OutputDir = "dist"
)

# 设置构建信息
$BuildTime = Get-Date -Format "yyyy-MM-ddTHH:mm:ssZ"
$GitCommit = if (git rev-parse --short HEAD 2>$null) { git rev-parse --short HEAD } else { "unknown" }

# 创建输出目录
if (!(Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

# 设置LDFLAGS
$LDFLAGS = "-X main.Version=$Version -X main.BuildTime=$BuildTime -X main.GitCommit=$GitCommit -s -w"

# 目标平台
$Platforms = @(
    @{ GOOS = "linux"; GOARCH = "amd64"; Suffix = "" },
    @{ GOOS = "linux"; GOARCH = "arm64"; Suffix = "" },
    @{ GOOS = "windows"; GOARCH = "amd64"; Suffix = ".exe" },
    @{ GOOS = "windows"; GOARCH = "arm64"; Suffix = ".exe" },
    @{ GOOS = "darwin"; GOARCH = "amd64"; Suffix = "" },
    @{ GOOS = "darwin"; GOARCH = "arm64"; Suffix = "" }
)

Write-Host "开始构建 SBManager Agent v$Version..." -ForegroundColor Green
Write-Host "构建时间: $BuildTime" -ForegroundColor Yellow
Write-Host "Git提交: $GitCommit" -ForegroundColor Yellow
Write-Host ""

# 清理旧的构建
Remove-Item "$OutputDir\*" -Force -ErrorAction SilentlyContinue

# 构建所有平台
foreach ($Platform in $Platforms) {
    $OutputName = "agent-$($Platform.GOOS)-$($Platform.GOARCH)$($Platform.Suffix)"
    
    Write-Host "构建 $($Platform.GOOS)/$($Platform.GOARCH)..." -ForegroundColor Cyan
    
    $env:GOOS = $Platform.GOOS
    $env:GOARCH = $Platform.GOARCH
    
    go build -ldflags $LDFLAGS -o "$OutputDir\$OutputName" .
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ 构建成功: $OutputName" -ForegroundColor Green
        
        # 计算文件大小
        $FileSize = (Get-Item "$OutputDir\$OutputName").Length
        $SizeMB = [math]::Round($FileSize / 1MB, 2)
        Write-Host "  文件大小: $SizeMB MB" -ForegroundColor Gray
        
        # 创建压缩包
        if ($Platform.GOOS -eq "windows") {
            Compress-Archive -Path "$OutputDir\$OutputName" -DestinationPath "$OutputDir\$($OutputName -replace '\.exe$', '.zip')" -Force
            Write-Host "  已创建压缩包: $($OutputName -replace '\.exe$', '.zip')" -ForegroundColor Green
        } else {
            # 使用7-Zip或tar创建压缩包
            if (Get-Command tar -ErrorAction SilentlyContinue) {
                tar -czf "$OutputDir\$($OutputName).tar.gz" -C $OutputDir $OutputName
                Write-Host "  已创建压缩包: $($OutputName).tar.gz" -ForegroundColor Green
            }
        }
    } else {
        Write-Host "✗ 构建失败: $OutputName" -ForegroundColor Red
    }
    
    Write-Host ""
}

# 创建校验和
if (Get-Command sha256sum -ErrorAction SilentlyContinue) {
    Get-FileHash "$OutputDir\*" -Algorithm SHA256 | Select-Object Hash, Filename | Out-File "$OutputDir\checksums.txt"
    Write-Host "已创建校验和文件: checksums.txt" -ForegroundColor Green
}

Write-Host "构建完成！" -ForegroundColor Green
Write-Host "输出文件位于: $OutputDir\" -ForegroundColor Yellow
Get-ChildItem $OutputDir | Format-Table Name, Length, LastWriteTime
