echo \documentclass{article}\begin{document}Hello, World!\end{document} > test.tex
latexmk -pdf test.tex
dir test.pdf