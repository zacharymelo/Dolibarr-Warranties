#!/bin/bash
set -e
cd "$(dirname "$0")"
VERSION=$(grep -oE "'[0-9]+\.[0-9]+\.[0-9]+'" module/core/modules/modWarrantySvc.class.php | tr -d "'")
cp -r module warrantysvc
zip -r "warrantysvc-${VERSION}.zip" warrantysvc
rm -rf warrantysvc
echo "Built warrantysvc-${VERSION}.zip"
