#!/usr/bin/env python

# -*- coding: utf-8 -*-
"""
DB Backup
Dumps MySQL database to be pushed to S3. DB root password is required as arg.
Required libs: sudo pip install python-dotenv tinys3
Edit crontab: sudo crontab -e
CodeStyling: PEP 257
Author: Nicolas Pulido
"""

# python
import sys
import os
import time
import subprocess
import json
# s3
import tinys3
# dot env
import dotenv

#app properties
class APP:
	NAMESPACE     = ''
	DB_HOST 	  = ''
	DB_NAME 	  = ''
	DB_PASS 	  = ''
	#S3
	S3_BUCKET	  = ''
	S3_ACCESS_KEY = ''
	S3_SECRET_KEY = ''

#shell colors, ansi shorcuts
class SCS:
	GREEN = '\033[92m'
	RED   = '\033[91m'
	CYAN  = '\033[96m'
	END   = '\033[0m'

# -------------------------------------------------------------------------------------------
def main():
	"""Main Function."""

	#args_num = len(sys.argv)

	#set current path
	project_dir = os.path.dirname(os.path.realpath(__file__))
	project_dir = os.path.abspath(os.path.join(project_dir, os.pardir))
	dotenv_file = os.path.join(project_dir, '.env')

	print SCS.CYAN + "Project path:" + project_dir + SCS.END
	print SCS.CYAN + "Asking app configurations to CLI..." + SCS.END

	#get app config from command line (webapp CLI)
	command = subprocess.Popen("php " + project_dir + "/app/cli/cli.php main appConfig", shell=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
	output  = command.stdout.read()
	#print output
	config  = json.loads(output)

	#set properties
	APP.NAMESPACE 	  = config["namespace"]
	APP.S3_BUCKET	  = config["aws"]["bucketPrefix"] + "-dbb"
	APP.S3_ACCESS_KEY = config["aws"]["accessKey"]
	APP.S3_SECRET_KEY = config["aws"]["secretKey"]
	#get from env vars
	APP.ENV     = os.environ.get("APP_ENV")
	APP.DB_HOST = "db"
	APP.DB_NAME = "app"
	APP.DB_USER = "root"
	APP.DB_PASS = "dev"

	print SCS.CYAN + APP.NAMESPACE + " [" + APP.ENV + "] DB: " + APP.DB_HOST

	#dir
	project_dir = os.path.dirname(os.path.realpath(__file__))
	project_dir = os.path.abspath(os.path.join(project_dir, os.pardir))

	file_stamp  = time.strftime('%d-%m-%Y')
	output 		= project_dir + "/db/dump_" + file_stamp + ".sql"

	print SCS.CYAN + "Dumping DB..." + SCS.END
	#exec commands
	os.system("mysqldump -h " + APP.DB_HOST + " -u " + APP.DB_USER + " -p" + APP.DB_PASS + " " + APP.DB_NAME + " > " + output)
	os.system("gzip -f " + output)
	#update output
	output += ".gz"

	#validate dump was created
	if os.path.getsize(output) < 1024:
		print SCS.RED + "Invalid compressed dump file." + SCS.END
		return

	# uplaod task
	print SCS.CYAN + "Uploading to S3 Bucket " + APP.S3_BUCKET + SCS.END
	#push to AWS s3
	save_name = APP.NAMESPACE + "-db/" + file_stamp + ".sql.gz"
	s3_upload_file(output, save_name)

	print SCS.CYAN + "Removing file..." + SCS.END
	#remove file
	os.remove(output)

	print SCS.GREEN + "Done!" + SCS.END

# -------------------------------------------------------------------------------------------
def s3_upload_file(file, save_name):
	"""Setup Tiny S3 lib and upload file."""
	# Specifying a default bucket
	conn = tinys3.Connection(APP.S3_ACCESS_KEY, APP.S3_SECRET_KEY, APP.S3_BUCKET)

	# Uploading a single file and set private access
	f = open(file, "rb")
	conn.upload(save_name, f, public=False)

# -------------------------------------------------------------------------------------------
# Execute main
if __name__ == "__main__":
	main()
