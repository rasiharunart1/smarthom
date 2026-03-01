$exclude = @(
    "node_modules",
    "vendor",
    ".git",
    ".idea",
    ".vscode",
    "storage/framework/cache/data",
    "storage/framework/sessions",
    "storage/framework/views",
    "storage/logs",
    "public/hot",
    "deploy/project_deployment.zip",
    "*.log",
    "temp_log.txt",
    "smarthomeclone"
)

$source = "c:\Users\ACER\Documents\diklat.mdpower.io"
$destination = "c:\Users\ACER\Documents\diklat.mdpower.io\deploy\project_deployment.zip"

if (Test-Path $destination) {
    Remove-Item $destination
}

# Simple compress all except heavy folders (PowerShell Compress-Archive does not support robust exclude easily on * wildcard)
# Alternative: Use 7-Zip if available or loop.
# Simplified approach: Just tell user to zip it, or try to zip select folders.

# Let's try 7zip simply? No.
# Let's list items and exclude manually.

$items = Get-ChildItem -Path $source | Where-Object { 
    $_.Name -notin $exclude
}

Compress-Archive -Path $items.FullName -DestinationPath $destination -CompressionLevel Optimal
