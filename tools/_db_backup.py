#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Dumps MySQL database to be pushed to S3. DB root password is required as arg.
tinys3 lib is required -> pip install tinys3
For cronJobs using crontab: sudo crontab -u ubuntu -e
@author: Nicolas Pulido
"""

import sys
import os
import time
import tinys3
import subprocess
import json

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
	"""Main Function"""

	#args_num = len(sys.argv)

	#set current path
	project_dir = os.path.dirname(os.path.realpath(__file__))
	project_dir = os.path.abspath(os.path.join(project_dir, os.pardir))

	print SCS.CYAN + "Asking app configurations to CLI..." + SCS.END

	#get app config from command line (webapp CLI)
	#TODO: call env vars
	command = subprocess.Popen("php "+project_dir+"/cli/cli.php main appConfig", shell=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
	output  = command.stdout.read()
	#print output
	config  = json.loads(output)

	#set properties
	APP.NAMESPACE = config['app']['namespace']
	APP.DB_HOST   = os.environ.get('DB_HOST')
	APP.DB_NAME   = os.environ.get('DB_NAME')
	APP.DB_USER   = os.environ.get('DB_USER')
	APP.DB_PASS   = os.environ.get('DB_PASS')

	#s3
	APP.S3_BUCKET	  = config['app']['aws']['s3Bucket']
	APP.S3_ACCESS_KEY = config['app']['aws']['accessKey']
	APP.S3_SECRET_KEY = config['app']['aws']['secretKey']

	#dir
	project_dir = os.path.dirname(os.path.realpath(__file__))
	project_dir = os.path.abspath(os.path.join(project_dir, os.pardir))

	file_stamp  = time.strftime('%d-%m-%Y')
	output 		= project_dir + "/db/_dump_" + file_stamp + ".sql"

	print SCS.CYAN + "Dumping DB..." + SCS.END
	#exec commands
	os.system("mysqldump -h " + APP.DB_HOST + " -u root -p" + APP.DB_PASS + " " + APP.DB_NAME + " > " + output)
	os.system("gzip -f " + output)
	#update output
	output += ".gz"

	#validate dump was created
	if os.path.getsize(output) < 1024:
		print SCS.RED + "Invalid compressed dump file." + SCS.END
		return

	print SCS.CYAN + "Uploading to S3..." + SCS.END
	#push to AWS s3
	save_name = APP.NAMESPACE + "/" + file_stamp + ".sql.gz"
	s3_upload_file(output, save_name)

	print SCS.CYAN + "Removing file..." + SCS.END
	#remove file
	os.remove(output)

	print SCS.GREEN + "Script successfully executed!" + SCS.END

# -------------------------------------------------------------------------------------------
def s3_upload_file(file, save_name):
	"""Setup Tiny S3 lib and upload file"""
	# Specifying a default bucket
	conn = tinys3.Connection(APP.S3_ACCESS_KEY, APP.S3_SECRET_KEY, APP.S3_BUCKET)

	# Uploading a single file and set private access
	f = open(file, 'rb')
	conn.upload(save_name, f, public=False)

# -------------------------------------------------------------------------------------------
# Execute main
if __name__ == "__main__":
	main()
