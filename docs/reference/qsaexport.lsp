;;; ============================================================================
;;; QSAEXPORT.LSP - QSA Configuration Data Extractor
;;; ============================================================================
;;; Purpose:  Extract engraving element positions from AutoCAD drawings and
;;;           generate CSV files for import into the lw_quad_qsa_config table.
;;;
;;; Usage:    Load this file into AutoCAD LT 2026, then type QSAEXPORT
;;;
;;; Requirements:
;;;   - Layer "Q-Engrave" must exist with MTEXT entities
;;;   - Design identifier MTEXT at origin (0,0) with 5-character text (e.g., "STARa")
;;;   - Q0 element (QR code position) must be present
;;;
;;; Output:   {design_id}-qsa-config.csv in the same folder as the DWG file
;;;
;;; Author:   Quadica Developments
;;; Date:     January 2026
;;; ============================================================================

;;; ---------------------------------------------------------------------------
;;; Global Constants
;;; ---------------------------------------------------------------------------
(setq *QSA-LAYER* "Q-Engrave")        ; Layer containing engraving elements
(setq *QSA-TOLERANCE* 0.001)          ; Tolerance for origin detection (mm)
(setq *QSA-PRECISION* 4)              ; Decimal places for output values
(setq *QSA-QR-SIZE* 10)               ; Fixed element_size for QR code

;;; ---------------------------------------------------------------------------
;;; Utility Functions
;;; ---------------------------------------------------------------------------

;;; Round a number to specified decimal places
(defun qsa-round (num decimals / factor)
  (setq factor (expt 10.0 decimals))
  (/ (fix (+ (* num factor) 0.5)) factor)
)

;;; Convert radians to degrees
(defun qsa-rad-to-deg (radians)
  (qsa-round (* radians (/ 180.0 pi)) *QSA-PRECISION*)
)

;;; Format number to string with specified precision
(defun qsa-format-num (num decimals / str)
  (if num
    (progn
      (setq str (rtos (qsa-round num decimals) 2 decimals))
      str
    )
    "NULL"
  )
)

;;; Get current date/time as string M/D/Y HH:MM:SS
(defun qsa-get-timestamp (/ date-lst)
  (setq date-lst (getvar "CDATE"))
  (strcat
    (itoa (fix (/ (rem date-lst 10000.0) 100)))  ; Month
    "/"
    (itoa (fix (rem date-lst 100.0)))             ; Day
    "/"
    (itoa (fix (/ date-lst 10000.0)))             ; Year
    " "
    (substr (rtos (rem date-lst 1.0) 2 6) 3 2)    ; Hour
    ":"
    (substr (rtos (rem date-lst 1.0) 2 6) 5 2)    ; Minute
    ":00"                                          ; Seconds (approximation)
  )
)

;;; Escape commas and quotes in CSV field
(defun qsa-csv-escape (str)
  (if (or (vl-string-search "," str)
          (vl-string-search "\"" str))
    (strcat "\"" (vl-string-subst "\"\"" "\"" str) "\"")
    str
  )
)

;;; ---------------------------------------------------------------------------
;;; MTEXT Entity Data Extraction
;;; ---------------------------------------------------------------------------

;;; Get MTEXT text content
(defun qsa-get-mtext-content (ent / content)
  (setq content (cdr (assoc 1 (entget ent))))
  ;; Strip MTEXT formatting codes if present
  (if content
    (progn
      ;; Remove common formatting codes like {\fArial|...;text}
      (while (vl-string-search "\\P" content)
        (setq content (vl-string-subst "" "\\P" content))
      )
      (while (vl-string-search "\\p" content)
        (setq content (vl-string-subst "" "\\p" content))
      )
      ;; Strip curly brace formatting blocks
      (if (and (= (substr content 1 1) "{")
               (= (substr content (strlen content) 1) "}"))
        (progn
          (setq content (substr content 2 (- (strlen content) 2)))
          ;; Find semicolon that ends font specification
          (if (vl-string-search ";" content)
            (setq content (substr content (+ 2 (vl-string-search ";" content))))
          )
        )
      )
      content
    )
    ""
  )
)

;;; Get MTEXT insertion point (converted from WCS to UCS)
(defun qsa-get-mtext-insertion (ent / wcs-pt)
  (setq wcs-pt (cdr (assoc 10 (entget ent))))
  ;; Transform from WCS (0) to UCS (1)
  (if wcs-pt
    (trans wcs-pt 0 1)
    nil
  )
)

;;; Get MTEXT rotation in radians
(defun qsa-get-mtext-rotation (ent / rot)
  (setq rot (cdr (assoc 50 (entget ent))))
  (if rot rot 0.0)
)

;;; Get MTEXT text height
(defun qsa-get-mtext-height (ent)
  (cdr (assoc 40 (entget ent)))
)

;;; Check if point is at origin within tolerance
(defun qsa-at-origin-p (pt tolerance)
  (and pt
       (< (abs (car pt)) tolerance)
       (< (abs (cadr pt)) tolerance))
)

;;; ---------------------------------------------------------------------------
;;; Location Code Parsing
;;; ---------------------------------------------------------------------------

;;; Parse location code and return (element_type . position)
;;; Returns nil if not a valid location code
(defun qsa-parse-location-code (code / len first-char pos-str led-pos led-num)
  (setq len (strlen code))
  (cond
    ;; Q0 = qr_code at position 0
    ((= code "Q0")
     (cons "qr_code" 0)
    )
    ;; M1-M8 = module_id at positions 1-8
    ((and (= (substr code 1 1) "M")
          (= len 2)
          (member (substr code 2 1) '("1" "2" "3" "4" "5" "6" "7" "8")))
     (cons "module_id" (atoi (substr code 2 1)))
    )
    ;; U1-U8 = serial_url at positions 1-8
    ((and (= (substr code 1 1) "U")
          (= len 2)
          (member (substr code 2 1) '("1" "2" "3" "4" "5" "6" "7" "8")))
     (cons "serial_url" (atoi (substr code 2 1)))
    )
    ;; S1-S8 = micro_id at positions 1-8
    ((and (= (substr code 1 1) "S")
          (= len 2)
          (member (substr code 2 1) '("1" "2" "3" "4" "5" "6" "7" "8")))
     (cons "micro_id" (atoi (substr code 2 1)))
    )
    ;; N-LM = led_code_M at position N (e.g., 1-L1, 2-L3, 8-L9)
    ((and (>= len 4)
          (member (substr code 1 1) '("1" "2" "3" "4" "5" "6" "7" "8"))
          (= (substr code 2 2) "-L")
          (member (substr code 4 1) '("1" "2" "3" "4" "5" "6" "7" "8" "9")))
     (setq led-pos (atoi (substr code 1 1)))
     (setq led-num (atoi (substr code 4 1)))
     (cons (strcat "led_code_" (itoa led-num)) led-pos)
    )
    ;; Not a recognized location code
    (t nil)
  )
)

;;; ---------------------------------------------------------------------------
;;; Selection Set Processing
;;; ---------------------------------------------------------------------------

;;; Get all MTEXT entities on Q-Engrave layer
(defun qsa-get-layer-mtext (/ ss)
  (ssget "X" (list (cons 0 "MTEXT") (cons 8 *QSA-LAYER*)))
)

;;; Find design identifier MTEXT at origin
(defun qsa-find-design-id (ss / i ent pt content design-id)
  (setq design-id nil)
  (if ss
    (progn
      (setq i 0)
      (while (and (not design-id) (< i (sslength ss)))
        (setq ent (ssname ss i))
        (setq pt (qsa-get-mtext-insertion ent))
        (if (qsa-at-origin-p pt *QSA-TOLERANCE*)
          (progn
            (setq content (qsa-get-mtext-content ent))
            (if (= (strlen content) 5)
              (setq design-id content)
            )
          )
        )
        (setq i (1+ i))
      )
    )
  )
  design-id
)

;;; Process all MTEXT entities and build data list
;;; Returns list of: ((element_type position origin_x origin_y rotation text_height element_size) ...)
(defun qsa-process-entities (ss / i ent pt content parsed element-type position
                                  rotation height data-list has-q0)
  (setq data-list nil)
  (setq has-q0 nil)

  (if ss
    (progn
      (setq i 0)
      (while (< i (sslength ss))
        (setq ent (ssname ss i))
        (setq pt (qsa-get-mtext-insertion ent))
        (setq content (qsa-get-mtext-content ent))

        ;; Skip the design identifier at origin
        (if (not (qsa-at-origin-p pt *QSA-TOLERANCE*))
          (progn
            (setq parsed (qsa-parse-location-code content))
            (if parsed
              (progn
                (setq element-type (car parsed))
                (setq position (cdr parsed))
                (setq rotation (qsa-rad-to-deg (qsa-get-mtext-rotation ent)))
                (setq height (qsa-get-mtext-height ent))

                ;; Track if Q0 was found
                (if (= content "Q0")
                  (setq has-q0 T)
                )

                ;; Build data record
                ;; (element_type position origin_x origin_y rotation text_height element_size)
                (setq data-list
                  (cons
                    (list
                      element-type
                      position
                      (car pt)                                    ; origin_x
                      (cadr pt)                                   ; origin_y
                      rotation                                    ; rotation in degrees
                      (cond                                       ; text_height
                        ((= element-type "qr_code") nil)
                        ((= element-type "micro_id") nil)
                        (t height)
                      )
                      (if (= element-type "qr_code")              ; element_size
                        *QSA-QR-SIZE*
                        nil
                      )
                    )
                    data-list
                  )
                )
              )
            )
          )
        )
        (setq i (1+ i))
      )
    )
  )

  ;; Return (data-list . has-q0)
  (cons (reverse data-list) has-q0)
)

;;; ---------------------------------------------------------------------------
;;; CSV File Generation
;;; ---------------------------------------------------------------------------

;;; Generate CSV content from data list
(defun qsa-generate-csv (design-id data-list / qsa-design revision timestamp csv-lines record)
  ;; Extract design and revision from 5-char design-id
  (setq qsa-design (substr design-id 1 4))
  (setq revision (substr design-id 5 1))
  (setq timestamp (qsa-get-timestamp))

  ;; CSV Header
  (setq csv-lines
    (list "qsa_design,revision,position,element_type,origin_x,origin_y,rotation,text_height,element_size,is_active,created_at,updated_at,created_by")
  )

  ;; Process each data record
  (foreach record data-list
    (setq csv-lines
      (cons
        (strcat
          qsa-design ","                                              ; qsa_design
          revision ","                                                ; revision
          (itoa (nth 1 record)) ","                                   ; position
          (nth 0 record) ","                                          ; element_type
          (qsa-format-num (nth 2 record) *QSA-PRECISION*) ","        ; origin_x
          (qsa-format-num (nth 3 record) *QSA-PRECISION*) ","        ; origin_y
          (qsa-format-num (nth 4 record) *QSA-PRECISION*) ","        ; rotation
          (qsa-format-num (nth 5 record) *QSA-PRECISION*) ","        ; text_height
          (if (nth 6 record) (itoa (nth 6 record)) "NULL") ","       ; element_size
          "1,"                                                        ; is_active
          timestamp ","                                               ; created_at
          "NULL,"                                                     ; updated_at
          "1"                                                         ; created_by
        )
        csv-lines
      )
    )
  )

  ;; Return lines in correct order (header first)
  (reverse csv-lines)
)

;;; Write CSV lines to file
(defun qsa-write-csv (filename csv-lines / fp line)
  (setq fp (open filename "w"))
  (if fp
    (progn
      (foreach line csv-lines
        (write-line line fp)
      )
      (close fp)
      T
    )
    nil
  )
)

;;; ---------------------------------------------------------------------------
;;; Main Command Function
;;; ---------------------------------------------------------------------------

(defun c:QSAEXPORT (/ ss design-id result data-list has-q0 csv-lines
                      dwg-path output-filename)

  ;; Get all MTEXT on Q-Engrave layer
  (setq ss (qsa-get-layer-mtext))

  (if (not ss)
    (progn
      (princ "\nError: No MTEXT entities found on layer 'Q-Engrave'.")
      (princ)
    )
    (progn
      ;; Find design identifier at origin
      (setq design-id (qsa-find-design-id ss))

      (if (not design-id)
        (progn
          (princ "\nError: Design identifier not found at origin (0,0).")
          (princ "\nExpected: 5-character MTEXT (e.g., 'STARa') within 0.001mm of origin.")
          (princ)
        )
        (progn
          ;; Process all entities
          (setq result (qsa-process-entities ss))
          (setq data-list (car result))
          (setq has-q0 (cdr result))

          (if (not has-q0)
            (progn
              (princ "\nError: Required element 'Q0' (QR code position) not found.")
              (princ)
            )
            (progn
              (if (= (length data-list) 0)
                (progn
                  (princ "\nError: No valid location codes found on Q-Engrave layer.")
                  (princ)
                )
                (progn
                  ;; Generate CSV content
                  (setq csv-lines (qsa-generate-csv design-id data-list))

                  ;; Determine output path
                  (setq dwg-path (getvar "DWGPREFIX"))
                  (setq output-filename (strcat dwg-path design-id "-qsa-config.csv"))

                  ;; Write file
                  (if (qsa-write-csv output-filename csv-lines)
                    (progn
                      ;; Silent success - no message per requirements
                      ;; Uncomment next line for debugging:
                      ;; (princ (strcat "\nExported " (itoa (length data-list)) " elements to: " output-filename))
                    )
                    (progn
                      (princ (strcat "\nError: Could not write file: " output-filename))
                    )
                  )
                )
              )
            )
          )
        )
      )
    )
  )
  (princ)
)

;;; ---------------------------------------------------------------------------
;;; Debug Command - Shows all MTEXT entities on Q-Engrave layer
;;; ---------------------------------------------------------------------------

(defun c:QSADEBUG (/ ss i ent pt wcs-pt content raw-content attachment)
  (princ "\n=== QSADEBUG: Inspecting Q-Engrave layer ===")
  (princ "\n(Coordinates shown in UCS - transformed from WCS)")

  (setq ss (qsa-get-layer-mtext))

  (if (not ss)
    (princ "\nNo MTEXT entities found on Q-Engrave layer.")
    (progn
      (princ (strcat "\nFound " (itoa (sslength ss)) " MTEXT entities:\n"))
      (setq i 0)
      (while (< i (sslength ss))
        (setq ent (ssname ss i))
        (setq wcs-pt (cdr (assoc 10 (entget ent))))
        (setq pt (qsa-get-mtext-insertion ent))  ; This now returns UCS coords
        (setq raw-content (cdr (assoc 1 (entget ent))))
        (setq content (qsa-get-mtext-content ent))
        (setq attachment (cdr (assoc 71 (entget ent))))

        (princ (strcat "\n[" (itoa (1+ i)) "] "))
        (princ (strcat "UCS: (" (rtos (car pt) 2 4) ", " (rtos (cadr pt) 2 4) ")"))
        (princ (strcat "  WCS: (" (rtos (car wcs-pt) 2 4) ", " (rtos (cadr wcs-pt) 2 4) ")"))
        (princ (strcat "\n    Attachment point: " (itoa (if attachment attachment 0))))
        (princ (strcat "\n    Raw content: \"" (if raw-content raw-content "nil") "\""))
        (princ (strcat "\n    Stripped content: \"" content "\""))
        (princ (strcat " (length: " (itoa (strlen content)) ")"))

        ;; Check if this could be the design ID
        (if (qsa-at-origin-p pt *QSA-TOLERANCE*)
          (princ "\n    ** AT ORIGIN (within tolerance) **")
          (princ (strcat "\n    Distance from UCS origin: "
                        (rtos (sqrt (+ (* (car pt) (car pt)) (* (cadr pt) (cadr pt)))) 2 6) " mm"))
        )

        (setq i (1+ i))
      )
    )
  )
  (princ "\n\n=== End QSADEBUG ===")
  (princ)
)

;;; ---------------------------------------------------------------------------
;;; Load Message
;;; ---------------------------------------------------------------------------
(princ "\nQSAEXPORT loaded. Type QSAEXPORT to extract QSA configuration data.")
(princ "\nType QSADEBUG to inspect MTEXT entities on Q-Engrave layer.")
(princ)

;;; ============================================================================
;;; End of QSAEXPORT.LSP
;;; ============================================================================
