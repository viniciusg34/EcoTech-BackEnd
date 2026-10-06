@echo off
REM Roda o teste do banco do Aiven e salva o resultado em resultado_banco.txt
cd /d "%~dp0.."
"C:\xampp\php\php.exe" ferramentas\ver_banco.php > ferramentas\resultado_banco.txt 2>&1
type ferramentas\resultado_banco.txt
echo.
echo Resultado salvo em ferramentas\resultado_banco.txt
pause
