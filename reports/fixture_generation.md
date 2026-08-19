# Fixture generation

## Source

| Metric                    | Value |
|---------------------------|-------|
| Scanned source files once | 22602 |

## Run

| Fixer            | Input files | Flavours | Selected | Created old | Removed | Verified | Updated | Stale | Old-only | Failures |
|------------------|-------------|----------|----------|-------------|---------|----------|---------|-------|----------|----------|
| exception-output | 22602       | 83       | 62       | 0           | 0       | 88       | 0       | 1     | 0        | 0        |
| final-newline    | 22602       | 2        | 2        | 0           | 0       | 2        | 0       | 0     | 0        | 0        |

## Details

| Fixer            | Fixtures                               | Reports                         |
|------------------|----------------------------------------|---------------------------------|
| exception-output | tests/Fixtures/exception_output_styles | reports/exception_output_styles |
| final-newline    | tests/Fixtures/final_newline           | reports/fixture_generation.md   |

## Failures
- none
