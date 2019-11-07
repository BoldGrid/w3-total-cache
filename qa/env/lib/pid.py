import os
import os.path



def create_file():
	with open("./working/pid_" + str(os.getpid()), 'w') as stream:
		stream.write('pid file')



def is_file_exists():
	return os.path.isfile("./working/pid_" + str(os.getpid()))
