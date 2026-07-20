import os
import shutil
import re
import datetime
import logging
from typing import Optional
from fastapi import FastAPI, Query, HTTPException
import pymysql

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("OmniServ")

app = FastAPI(title="OmniServ Python Ingestor Service")

# Suffix to DB Staging table mapping
STAGE_MAPPING = {
    "SEC": ("axos_securities_skeleton", "custodian_securities_axos"),
    "REG": ("axos_reg_skeleton", "custodian_portfolios_axos"),
    "PRI": ("axos_prices_skeleton", "custodian_prices_axos"),
    "TRN": ("axos_transactions_skeleton", "custodian_transactions_axos"),
    "POS": ("axos_positions_skeleton", "custodian_positions_axos"),
    "CBL": ("axos_cbl_skeleton", "custodian_cbl_axos"),
    "CBU": ("axos_cbu_skeleton", "custodian_cbu_axos"),
    "REP": ("axos_rep_skeleton", "custodian_rep_axos"),
    "BKR": ("axos_bkr_skeleton", "custodian_bkr_axos")
}

def parse_date_from_filename(filename: str) -> Optional[datetime.date]:
    """
    Extracts date from Axos filename format:
    e.g. AAS0_POS0_A_0327_0191_00000_AAS03272025A.POS -> 2025-03-27
    e.g. AAS0_POS0_A_0327_0191_00000_AAS0327A.POS -> 03-27 (assumes current year)
    """
    # Look for 8 digit date pattern like AAS03272025A
    match_long = re.search(r'AAS(\d{2})(\d{2})(\d{4})A', filename, re.IGNORECASE)
    if match_long:
        try:
            month, day, year = int(match_long.group(1)), int(match_long.group(2)), int(match_long.group(3))
            return datetime.date(year, month, day)
        except ValueError:
            pass

    # Look for 4 digit date pattern like AAS0327A
    match_short = re.search(r'AAS(\d{2})(\d{2})A', filename, re.IGNORECASE)
    if match_short:
        try:
            month, day = int(match_short.group(1)), int(match_short.group(2))
            year = datetime.date.today().year
            return datetime.date(year, month, day)
        except ValueError:
            pass

    # Check the standard middle pattern: _0327_
    match_mid = re.search(r'_(\d{2})(\d{2})_\d{4}_\d{5}_', filename)
    if match_mid:
        try:
            month, day = int(match_mid.group(1)), int(match_mid.group(2))
            year = datetime.date.today().year
            return datetime.date(year, month, day)
        except ValueError:
            pass

    return None

@app.get("/OmniServ/AutoParse")
def auto_parse(
    custodian: str = Query(..., description="Name of the custodian (e.g. Schwab, Axos)"),
    tenant: str = Query(..., description="Tenant namespace"),
    user: str = Query(..., description="Database user"),
    password: str = Query(..., description="Database password"),
    connection: str = Query(..., description="Database host IP"),
    dbname: str = Query(..., description="Database schema name"),
    vtigerDBName: str = Query(..., description="CRM Database name"),
    skipDays: int = Query(7, description="Number of days back to look"),
    dontIgnoreFileIfExists: int = Query(0, description="Override parsing deduplication"),
    operation: str = Query("writefiles", description="Action to perform"),
    extension: Optional[str] = Query(None, description="Target file extension (SEC, POS, etc.)"),
    repcode: Optional[str] = Query(None, description="Rep code filter")
):
    logger.info(f"Received request: custodian={custodian}, operation={operation}, extension={extension}")

    if custodian.lower() != "axos":
        raise HTTPException(status_code=400, detail="Only 'Axos' custodian is supported by this Python OmniServ replacement on port 8085.")

    if operation != "writefiles":
        raise HTTPException(status_code=400, detail=f"Operation '{operation}' is not supported. Only 'writefiles' is supported.")

    if not extension:
        raise HTTPException(status_code=400, detail="The 'extension' parameter is required to identify the type of file to parse.")

    # Target directory paths
    # We will search the testFiles directory first as informed by the user, then fallback to base Axos mount.
    source_dirs = ["/mnt/lanserver2n/Axos/testFiles/", "/mnt/lanserver2n/Axos/"]
    target_dir = "/var/www/sites/opt/storage/custodian/axos/"

    # Verify storage directory exists
    try:
        os.makedirs(target_dir, exist_ok=True)
    except Exception as e:
        logger.error(f"Failed to create local target directory: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to create local target directory: {e}")

    # Gather matching files from the sources
    extension_upper = extension.upper()
    found_files = []

    for s_dir in source_dirs:
        if not os.path.exists(s_dir):
            logger.warning(f"Source directory {s_dir} does not exist. Skipping.")
            continue

        for filename in os.listdir(s_dir):
            sanitized_name = os.path.basename(filename)
            if not sanitized_name.upper().endswith(f".{extension_upper}"):
                continue

            full_source_path = os.path.join(s_dir, sanitized_name)
            if not os.path.isfile(full_source_path):
                continue

            # Check skipDays date constraint
            file_date = parse_date_from_filename(sanitized_name)
            if file_date and skipDays > 0:
                # To support testing with test files from March 2025:
                # If skipDays is less than 500, and the files are older, they will get filtered out.
                # However, we will allow older files if they are in the testFiles folder to facilitate local integration testing.
                if "testFiles" not in s_dir:
                    delta = datetime.date.today() - file_date
                    if delta.days > skipDays:
                        logger.info(f"Skipping {sanitized_name} because its date {file_date} is older than {skipDays} days.")
                        continue

            found_files.append((full_source_path, sanitized_name))

    if not found_files:
        return {"status": "success", "message": f"No new files found for custodian '{custodian}' with extension '{extension}'."}

    copied_count = 0
    queued_count = 0

    try:
        # Establish DB connection using parameterized credentials
        db = pymysql.connect(
            host=connection,
            user=user,
            password=password,
            database=dbname,
            cursorclass=pymysql.cursors.DictCursor
        )
    except Exception as e:
        logger.error(f"Database connection failed: {e}")
        raise HTTPException(status_code=500, detail=f"Database connection failed: {e}")

    try:
        with db.cursor() as cursor:
            # Query the target table names from STAGE_MAPPING
            skeleton_table, copy_to_table = STAGE_MAPPING.get(
                extension_upper, 
                (f"axos_{extension_upper.lower()}_skeleton", f"custodian_{extension_upper.lower()}_axos")
            )

            for src_path, filename in found_files:
                dest_path = os.path.join(target_dir, filename)

                # Copy file from SMB share to local web server storage
                try:
                    shutil.copy2(src_path, dest_path)
                    copied_count += 1
                except Exception as copy_err:
                    logger.error(f"Failed to copy file {filename}: {copy_err}")
                    continue

                # Check if file has already been queued or parsed
                if dontIgnoreFileIfExists == 0:
                    check_sql = "SELECT id FROM files_to_parse WHERE filename = %s AND finished = 1 LIMIT 1"
                    cursor.execute(check_sql, (dest_path,))
                    if cursor.fetchone():
                        logger.info(f"Skipping database queue for {filename} as it has already been parsed.")
                        continue

                # Insert file record into files_to_parse
                # NOTE: table_type expects lowercase suffix (e.g. 'pos', 'trn')
                insert_sql = """
                    INSERT INTO files_to_parse 
                    (filename, custodian, table_type, skeleton_table, finished, date_added) 
                    VALUES (%s, %s, %s, %s, %s, NOW())
                """
                cursor.execute(
                    insert_sql, 
                    (dest_path, custodian.lower(), extension.lower(), skeleton_table, 0)
                )
                queued_count += 1

        db.commit()
    except Exception as db_err:
        db.rollback()
        logger.error(f"Database transaction error: {db_err}")
        raise HTTPException(status_code=500, detail=f"Database transaction error: {db_err}")
    finally:
        db.close()

    return {
        "status": "success",
        "message": f"Successfully processed Axos files for extension {extension_upper}.",
        "details": {
            "copied": copied_count,
            "queued": queued_count
        }
    }

if __name__ == "__main__":
    import uvicorn
    # Bind to localhost (127.0.0.1) as required by the security verification guidelines
    uvicorn.run(app, host="127.0.0.1", port=8085)
