# NF-UX-09 — match the complete home pin appearance

The prior change matched SVG paths and category colors, but retained the Native rounded pale icon tile. This correction matches the complete home icon container: 32px colored teardrop pin, white glyph and border, shadow, rotation, 17px glyph and rounded stroke. The compact selected state retains that same appearance. Private map glyph reuse remains unchanged.

Runtime scope: native-report.css only. No canonical/model/Smart Address/media/publication change and no database writes or report submissions.

A real local browser test loads the actual home and Native CSS and compares computed pin and SVG styles for all six categories, then the visible selected pin after compaction, at 320/390/768/1440px. This specifically covers the previous gap between equal SVG geometry and unequal visible appearance. The existing gate also runs Native/Smart Address and map marker regressions before one-file staging deployment.
