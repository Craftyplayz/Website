import hashlib
import zipfile
import os
from pathlib import Path

# For PDF processing
try:
    import fitz  # PyMuPDF
    PDF_SUPPORT = True
except ImportError:
    PDF_SUPPORT = False
    print("Warning: PyMuPDF not installed. PDF support disabled.")
    print("Install with: pip install PyMuPDF")
    print()

def partial_md5(path):
    """Calculate partial MD5 hash of a file."""
    if not path:
        return ""
    
    try:
        with open(path, 'rb') as f:
            hasher = hashlib.md5()
            buf_size = 1024
            
            for i in range(-1, 11):
                offset = 1024 << (2 * i) if i >= 0 else 0
                
                try:
                    f.seek(offset)
                    chunk = f.read(buf_size)
                    if chunk:
                        hasher.update(chunk)
                    else:
                        break
                except:
                    break
            
            return hasher.hexdigest()
    except Exception as e:
        print(f"Error calculating MD5 for {path}: {e}")
        return ""

def extract_cover_from_pdf(pdf_path):
    """Extract first page from PDF as cover image. Returns (image_data, extension) or (None, None)."""
    if not PDF_SUPPORT:
        return None, None
    
    try:
        # Open the PDF
        doc = fitz.open(pdf_path)
        
        if len(doc) == 0:
            return None, None
        
        # Get the first page
        page = doc[0]
        
        # Render page to image (PNG format, 300 DPI for good quality)
        pix = page.get_pixmap(matrix=fitz.Matrix(300/72, 300/72))
        
        # Convert to PNG bytes
        img_data = pix.tobytes("png")
        
        doc.close()
        
        return img_data, ".png"
        
    except Exception as e:
        print(f"Error extracting cover from {pdf_path}: {e}")
        return None, None

def extract_cover_from_epub(epub_path):
    """Extract cover image from an EPUB file. Returns (image_data, extension) or (None, None)."""
    try:
        with zipfile.ZipFile(epub_path, 'r') as epub:
            # First, find the OPF file (package document)
            opf_path = None
            
            # Check container.xml for OPF location
            try:
                container_data = epub.read('META-INF/container.xml')
                import xml.etree.ElementTree as ET
                root = ET.fromstring(container_data)
                # Find the rootfile element
                for elem in root.iter():
                    if elem.tag.endswith('rootfile'):
                        opf_path = elem.get('full-path')
                        break
            except:
                # If container.xml doesn't exist, search for .opf files
                for name in epub.namelist():
                    if name.endswith('.opf'):
                        opf_path = name
                        break
            
            if opf_path:
                # Parse the OPF file to find cover
                opf_data = epub.read(opf_path)
                import xml.etree.ElementTree as ET
                root = ET.fromstring(opf_data)
                
                # Get the directory of the OPF file for resolving relative paths
                opf_dir = os.path.dirname(opf_path)
                
                cover_id = None
                cover_href = None
                
                # Method 1: Look for EPUB 3 style cover (properties="cover-image")
                for elem in root.iter():
                    if elem.tag.endswith('item'):
                        if elem.get('properties') == 'cover-image':
                            cover_href = elem.get('href')
                            break
                
                # Method 2: Look for EPUB 2 style cover (meta name="cover")
                if not cover_href:
                    for elem in root.iter():
                        if elem.tag.endswith('meta') and elem.get('name') == 'cover':
                            cover_id = elem.get('content')
                            break
                    
                    # Find the item with this ID
                    if cover_id:
                        for elem in root.iter():
                            if elem.tag.endswith('item') and elem.get('id') == cover_id:
                                cover_href = elem.get('href')
                                break
                
                # If we found a cover reference, extract it
                if cover_href:
                    # Resolve the path relative to OPF location
                    if opf_dir:
                        cover_path = os.path.join(opf_dir, cover_href).replace('\\', '/')
                    else:
                        cover_path = cover_href
                    
                    try:
                        cover_data = epub.read(cover_path)
                        ext = os.path.splitext(cover_path)[1]
                        return cover_data, ext
                    except:
                        pass
            
            # Fallback Method 1: Look for files with "cover" in the name
            for name in epub.namelist():
                if 'cover' in name.lower() and name.lower().endswith(('.jpg', '.jpeg', '.png', '.gif')):
                    cover_data = epub.read(name)
                    ext = os.path.splitext(name)[1]
                    return cover_data, ext
            
            # Fallback Method 2: Get the first image
            for name in epub.namelist():
                if name.lower().endswith(('.jpg', '.jpeg', '.png', '.gif')):
                    cover_data = epub.read(name)
                    ext = os.path.splitext(name)[1]
                    return cover_data, ext
            
            return None, None
            
    except Exception as e:
        print(f"Error extracting cover from {epub_path}: {e}")
        return None, None

def process_books(root_folder, covers_folder="covers"):
    """Process all EPUB and PDF files in root_folder and subfolders."""
    
    # Create covers folder if it doesn't exist
    covers_path = Path(covers_folder)
    covers_path.mkdir(exist_ok=True)
    
    # Find all EPUB and PDF files recursively
    root_path = Path(root_folder)
    epub_files = list(root_path.rglob("*.epub"))
    pdf_files = list(root_path.rglob("*.pdf"))
    
    all_files = epub_files + pdf_files
    
    print(f"Found {len(epub_files)} EPUB files and {len(pdf_files)} PDF files")
    print(f"Total: {len(all_files)} files\n")
    
    processed = 0
    failed = 0
    
    for book_file in all_files:
        file_type = book_file.suffix.lower()
        print(f"Processing: {book_file.name} ({file_type})")
        
        # Step 1: Generate partial MD5
        md5_hash = partial_md5(str(book_file))
        if not md5_hash:
            print(f"  ✗ Failed to generate MD5")
            failed += 1
            continue
        
        print(f"  MD5: {md5_hash}")
        
        # Step 2: Extract cover based on file type
        if file_type == '.epub':
            cover_data, ext = extract_cover_from_epub(str(book_file))
        elif file_type == '.pdf':
            cover_data, ext = extract_cover_from_pdf(str(book_file))
        else:
            print(f"  ✗ Unsupported file type")
            failed += 1
            continue
        
        if not cover_data:
            print(f"  ✗ No cover found")
            failed += 1
            continue
        
        # Step 3: Save cover with MD5 filename
        cover_filename = f"{md5_hash}{ext}"
        cover_path = covers_path / cover_filename
        
        with open(cover_path, 'wb') as f:
            f.write(cover_data)
        
        print(f"  ✓ Cover saved: {cover_filename}\n")
        processed += 1
    
    print(f"\n{'='*50}")
    print(f"Complete!")
    print(f"Successfully processed: {processed}")
    print(f"Failed: {failed}")
    print(f"Covers saved to: {covers_path.absolute()}")

if __name__ == "__main__":
    # Set your folder path here
    root_folder = r"D:\!Git\Kindle\Books"
    covers_folder = r"S:\koInsight\covers"
    
    process_books(root_folder, covers_folder)