#!/usr/bin/env python3
"""Re-frame the four rotating hero portraits so they show head AND shoulders,
the way the approved design does, instead of a face filling the whole frame.

The live crops were built to fill a 21:34 box from whatever the source was,
which on a three-quarter portrait like L.J. Battles threw away the suit, the
hands and the fence and left a passport photo. William: "you can see only the
faces."

Face is found with a Haar cascade; the frame is then placed around it so the
face box is a fixed fraction of the height and sits high enough to leave room
for a hat. Where the source runs out before the frame does, the frame shifts
rather than stretching, and only pads as a last resort."""
import os, cv2
from PIL import Image, ImageOps

SRC = "/var/lib/freelancer/projects/40608849"
OUT = os.path.join(SRC, "app/public/assets/hero-rot")
TW, TH = 420, 660                     # unchanged: the slots and the CSS expect this

# slug -> source file. Order matches p01..p13 in index.php.
JOBS = [
    ("p01", "L.J. Battles.jpg"),          ("p02", "Nathaniel Battles.jpg"),
    ("p03", "Susie Johnson.jpeg"),        ("p04", "Elizabeth Carey.jpg"),
    ("p05", "James(JT) Battles.jpg"),     ("p06", "Horatio Battles.jpg"),
    ("p07", "Settie Battles.jpg"),        ("p08", "Agustus (Gus) Battles.jpg"),
    ("p09", "Johnnie Mae Battles.jpg"),   ("p10", "Anthony  Battles.jpg"),
    ("p11", "Sam Calvin Battles.jpg"),    ("p12", "Edmond Battles.jpg"),
    ("p13", "Louisa Battles.jpg"),
]

# how tall the detected face box should be as a fraction of the finished frame,
# and where its centre sits vertically. A hat needs the face pushed further down.
FACE_H, FACE_Y = 0.30, 0.36
TWEAK = {}                            # slug -> (face_h, face_y) override

CASCADE = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_alt2.xml")

def find_face(path):
    img = cv2.imread(path)
    if img is None: return None
    g = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    g = cv2.equalizeHist(g)
    for sf, mn in ((1.05, 5), (1.10, 4), (1.20, 3), (1.30, 2)):
        f = CASCADE.detectMultiScale(g, scaleFactor=sf, minNeighbors=mn,
                                     minSize=(int(min(g.shape)*0.08),)*2)
        if len(f): return sorted(f, key=lambda r: r[2]*r[3])[-1]   # biggest
    return None

def frame(im, face, fh, fy):
    """The loosest crop this photograph can actually give, never a padded one.

    Settie's picture is already a tight square and Sam Calvin's is a head — there
    is no jacket in the file to reveal, and a grey band pretending to be one looks
    worse than the close crop did. So the requested frame is shrunk to fit inside
    the source, and only then positioned around the face."""
    W, H = im.size
    fx, fyy, fw, fhh = face
    out_h = fhh / fh                       # what the framing rule asks for
    out_w = out_h * (TW / TH)
    if out_h > H:  out_h, out_w = H, H * (TW / TH)      # taller than the photo
    if out_w > W:  out_w, out_h = W, W * (TH / TW)      # wider than the photo
    top  = (fyy + fhh/2) - out_h * fy
    left = (fx + fw/2) - out_w / 2
    left = max(0, min(left, W - out_w))
    top  = max(0, min(top,  H - out_h))
    reg = im.crop((round(left), round(top), round(left + out_w), round(top + out_h)))
    return reg.resize((TW, TH), Image.LANCZOS)

if __name__ == "__main__":
    os.makedirs(OUT, exist_ok=True)
    for slug, fn in JOBS:
        p = os.path.join(SRC, fn)
        if not os.path.isfile(p): print("MISSING", fn); continue
        face = find_face(p)
        im = ImageOps.exif_transpose(Image.open(p).convert("RGB"))
        if face is None:
            print("no face found:", slug, fn, "-> left as it was"); continue
        fh, fy = TWEAK.get(slug, (FACE_H, FACE_Y))
        out = frame(im, face, fh, fy)
        out = ImageOps.autocontrast(out, cutoff=1)
        out.save(os.path.join(OUT, slug + ".jpg"), "JPEG", quality=88, optimize=True)
        print("%s  %-28s face=%s" % (slug, fn, tuple(int(v) for v in face)))
